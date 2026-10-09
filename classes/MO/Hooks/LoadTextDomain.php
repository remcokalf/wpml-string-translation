<?php

namespace WPML\ST\MO\Hooks;

use WPML\ST\MO\File\Manager;
use WPML\ST\MO\LoadedMODictionary;
use WPML_ST_Translations_File_Locale;
use function WPML\FP\partial;
use WPML\LIB\WP\WordPress;
use WPML\ST\MO\Hooks\LoadTranslationFile;

class LoadTextDomain implements \IWPML_Action {

	const PRIORITY_OVERRIDE = 10;

	private $file_manager;

	private $file_locale;

	private $loaded_mo_dictionary;

	private $loaded_domains = [];

	public function __construct(
		Manager $file_manager,
		WPML_ST_Translations_File_Locale $file_locale,
		LoadedMODictionary $loaded_mo_dictionary
	) {
		$this->file_manager         = $file_manager;
		$this->file_locale          = $file_locale;
		$this->loaded_mo_dictionary = $loaded_mo_dictionary;
	}

	public function add_hooks() {
		$this->reloadAlreadyLoadedMOFiles();

		add_filter( 'override_load_textdomain', [ $this, 'overrideLoadTextDomain' ], 10, 3 );
		add_filter( 'override_unload_textdomain', [ $this, 'overrideUnloadTextDomain' ], 10, 2 );
		add_action( 'wpml_language_has_switched', [ $this, 'languageHasSwitched' ] );
	}

	public function overrideLoadTextDomain( $override, $domain, $mofile ) {
		if ( ! $mofile ) {
			return $override;
		}


		if ( ! $this->isCustomMOLoaded( $domain ) ) {
			remove_filter( 'override_load_textdomain', [ $this, 'overrideLoadTextDomain' ], 10 );
			$locale = $this->file_locale->get( $mofile, $domain );
			$this->fallbackDefaultTranslations( $mofile, $domain, $locale );
			$this->loadCustomMOFile( $domain, $mofile, $locale );
			add_filter( 'override_load_textdomain', [ $this, 'overrideLoadTextDomain' ], 10, 3 );
		}

		$this->loaded_mo_dictionary->addFile( $domain, $mofile );

		return $override;
	}

	public function overrideUnloadTextDomain( $override, $domain ) {
		$key = array_search( $domain, $this->loaded_domains );

		if ( false !== $key ) {
			unset( $this->loaded_domains[ $key ] );
		}

		return $override;
	}

	private function isCustomMOLoaded( $domain ) {
		return in_array( $domain, $this->loaded_domains, true );
	}

	private function loadCustomMOFile( $domain, $mofile, $locale ) {
		$wpml_mofile = $this->file_manager->get( $domain, $locale );

		if ( $wpml_mofile && $wpml_mofile !== $mofile ) {
			$defaultTextdomainPath = LoadTranslationFile::getDefaultWordPressTranslationPath( $domain, $locale );

			load_textdomain( $domain, $wpml_mofile );

			if ( $defaultTextdomainPath ) {
				$this->maybeLoadWordPressJITMoFile( $defaultTextdomainPath, $domain );
			}
		}

		$this->setCustomMOLoaded( $domain );
	}

	private function maybeLoadWordPressJITMoFile( $path, $domain ) {
		if( file_exists( $path ) ) {
			load_textdomain( $domain, $path );
		}
	}

	private function reloadAlreadyLoadedMOFiles() {
		$this->loaded_mo_dictionary->getEntities()->each( function ( $entity ) {
			unload_textdomain( $entity->domain );
			$locale = $this->file_locale->get( $entity->mofile, $entity->domain );
			$this->loadCustomMOFile( $entity->domain, $entity->mofile, $locale );
			if ( class_exists( '\WP_Translation_Controller' ) ) {
				load_textdomain( $entity->domain, $entity->mofile, $locale );
			} else {
				load_textdomain($entity->domain, $entity->mofile);
			}
		} );
	}

	private function setCustomMOLoaded( $domain ) {
		$this->loaded_domains[] = $domain;
	}

	public function languageHasSwitched() {
		$this->loaded_domains = [];
	}

	public function fallbackDefaultTranslations( $mofile, $domain, $locale) {
		if (WordPress::versionCompare('>', '6.6.999') && is_string( $mofile )) {
			$wpml_mofile = $this->file_manager->get($domain, $locale);

			$replaced_mofile = LoadTranslationFile::replaceMoExtensionWithPhp( $mofile );
			if (!file_exists($mofile) && !file_exists($replaced_mofile) && $wpml_mofile) {
				$defaultTranslationsFile = LoadTranslationFile::getDefaultWordPressTranslationPath($domain, $locale) ?: $wpml_mofile;
				LoadTranslationFile::replaceTranslationFile($domain, $mofile, $defaultTranslationsFile);
			}
		}
	}
}
