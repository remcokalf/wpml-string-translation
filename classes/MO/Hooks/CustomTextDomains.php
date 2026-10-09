<?php

namespace WPML\ST\MO\Hooks;

use WPML\FP\Lst;
use WPML\ST\MO\File\Manager;
use WPML\ST\MO\JustInTime\MO;
use WPML\ST\MO\LoadedMODictionary;
use WPML\ST\Storage\StoragePerLanguageInterface;
use WPML\ST\TranslationFile\Domains;
use function WPML\FP\pipe;
use function WPML\FP\spreadArgs;
use WPML_Locale;

class CustomTextDomains implements \IWPML_Action {
	const CACHE_ID  = 'wpml-st-custom-mo-files';
	const CACHE_ALL_LOCALES = 'locales';
	const CACHE_KEY_DOMAINS = 'domains';
	const CACHE_KEY_FILES = 'files';

	private $manager;

	private $domains;

	private $loadedDictionary;

	private $cache;

	private $locale;

	private $syncMissingFile;

	private $loaded_custom_domains = [];

	public function __construct(
		Manager $file_manager,
		Domains $domains,
		LoadedMODictionary $loadedDictionary,
		StoragePerLanguageInterface $cache,
		WPML_Locale $locale,
		?callable $syncMissingFile = null
	) {
		$this->manager          = $file_manager;
		$this->domains          = $domains;
		$this->loadedDictionary = $loadedDictionary;
		$this->cache            = $cache;
		$this->locale           = $locale;
		$this->syncMissingFile  = $syncMissingFile ?: function () {};

		add_action( 'wpml_st_translation_file_written', [ $this, 'clear_cache' ], 10, 0 );
		add_action( 'wpml_st_translation_file_removed', [ $this, 'clear_cache' ], 10, 0 );
		add_action( 'wpml_st_translation_file_updated', [ $this, 'clear_cache' ], 10, 0 );
	}

	public function clear_cache() {
		$locales = $this->cache->get( self::CACHE_ALL_LOCALES );
		if ( ! is_array( $locales ) ) {
			return;
		}

		foreach ( $locales as $locale ) {
			$this->cache->delete( $locale );
		}

		$this->cache->delete( self::CACHE_ALL_LOCALES );
	}

	public function add_hooks() {
		$this->init_custom_text_domains();
	}


	public function init_custom_text_domains( $locale = null ) {
		$locale = $locale ?: determine_locale();

		$addJitMoToL10nGlobal = pipe( Lst::nth( 0 ), function ( $domain ) use ( $locale ) {
			unset( $GLOBALS['l10n'][ $domain ] );

			$this->loaded_custom_domains[] = $domain;
			$GLOBALS['l10n'][ $domain ] = new MO( $this->loadedDictionary, $locale, $domain );
		} );

		$getDomainPathTuple = function ( $domain ) use ( $locale ) {
			return [ $domain, $this->manager->getFilepath( $domain, $locale ) ];
		};

		$cache = $this->cache->get( $locale );

		if ( isset( $cache[ self::CACHE_KEY_DOMAINS ] ) ) {
			$domains = \wpml_collect( $cache[ self::CACHE_KEY_DOMAINS ] );
		} else {
			$cache_update_required = true;

			$domains = \wpml_collect( $this->domains->getCustomMODomains( $locale ) );
		}

		$files = $domains->map( $getDomainPathTuple )
			->each( spreadArgs( $this->syncMissingFile ) )
			->each( spreadArgs( [ $this->loadedDictionary, 'addFile' ] ) );

		if ( isset( $cache[ self::CACHE_KEY_FILES ] ) ) {
			$localeFiles = \wpml_collect( $cache[ self::CACHE_KEY_FILES ] );
		} else {
			$cache_update_required = true;

			$isReadableFile = function ( $domainAndFilePath ) {
				return is_readable( $domainAndFilePath[1] );
			};
			$localeFiles = $files->filter( $isReadableFile );
		}

		if ( isset( $cache_update_required ) ) {
			$this->cache->save(
				$locale,
				[
					self::CACHE_KEY_DOMAINS => $domains->toArray(),
					self::CACHE_KEY_FILES   => $localeFiles->toArray(),
				]
			);

			$cache_locales = $this->cache->get( self::CACHE_ALL_LOCALES );
			$cache_locales = is_array( $cache_locales ) ? $cache_locales : [];
			if ( ! in_array( $locale, $cache_locales, true ) ) {
				$cache_locales[] = $locale;

				$this->cache->save(
					self::CACHE_ALL_LOCALES,
					array_unique( $cache_locales )
				);
			}
		}

		$localeFiles->each( $addJitMoToL10nGlobal );
	}
}
