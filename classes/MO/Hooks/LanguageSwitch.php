<?php

namespace WPML\ST\MO\Hooks;

use WPML\ST\MO\JustInTime\MOFactory;
use WPML\ST\MO\WPLocaleProxy;
use WPML\ST\MO\WPML_Locale;
use WPML\ST\Utils\LanguageResolution;

class LanguageSwitch implements \IWPML_Action {

	const MAX_CACHED_LOCALES = 150;

	const CACHE_BUDGET_MIN_BYTES     = 25165824;
	const CACHE_BUDGET_MAX_BYTES     = 1610612736;
	const CACHE_RESERVE_MIN_BYTES    = 201326592;
	const CACHE_RESERVE_RATIO        = 0.25;
	const CACHE_VALVE_RESERVE_MAX_BYTES = 201326592;
	const CACHE_VALVE_RESERVE_RATIO     = 0.25;
	const CATALOG_EXPANSION_FACTOR   = 2.0;
	const SNAPSHOT_BASE_BYTES        = 4096;
	const SNAPSHOT_PER_DOMAIN_BYTES  = 2048;
	const LEGACY_ENTRY_BYTES         = 640;

	private $jit_mo_factory;

	private $language_resolution;

	private static $current_locale;

	private static $base_locale;

	private static $locale_stack = [];

	private static $globals_cache = [];

	private static $globals_cache_bytes = [];

	private static $file_size_cache = [];

	private static $loaded_translations_property;

	private static $initial_locale;

	private static $pending_queue_processing_depth = 0;

	public function __construct(
		LanguageResolution $language_resolution,
		MOFactory $jit_mo_factory
	) {
		$this->language_resolution = $language_resolution;
		$this->jit_mo_factory      = $jit_mo_factory;
	}

	public function add_hooks() {
		add_action( 'wpml_language_has_switched', [ $this, 'languageHasSwitched' ], 10, 3 );
	}

	private function setCurrentLocale( $locale ) {
		self::$current_locale = $locale;
	}

	public function getCurrentLocale() {
		return self::$current_locale;
	}

	public function languageHasSwitched( $code = null, $cookie_lang = false, $original_language = null ) {
		if ( null === $code ) {
			$this->restoreLocale();
			return;
		}

		$this->pushLocale();

		$new_locale = $this->language_resolution->getCurrentLocale();
		if ( ! $new_locale ) {
			$this->switchToLocale( self::$base_locale );
			return;
		}
		$this->switchToLocale( $new_locale );
	}

	private function pushLocale() {
		$found = get_locale();

		if ( ! $this->getCurrentLocale() ) {
			$this->seed( $found, $found );
		}

		self::$locale_stack[] = $found;
	}

	private function restoreLocale() {
		$found = array_pop( self::$locale_stack );

		if ( ! $found ) {
			return;
		}

		$this->switchToLocale( $found );
	}

	public function initCurrentLocale() {
		if ( $this->getCurrentLocale() ) {
			return;
		}

		$locale = $this->language_resolution->getCurrentLocale();

		if ( ! $locale ) {
			return;
		}

		$this->seed( $locale, get_locale() );
	}

	private function seed( $locale, $base ) {
		self::$base_locale = $base;
		add_filter( 'locale', [ $this, 'filterLocale' ], PHP_INT_MAX );
		$this->setCurrentLocale( $locale );
		self::$initial_locale = $this->getCurrentLocale();
	}

	public function switchToLocale( $new_locale ) {
		if ( $new_locale === $this->getCurrentLocale() ) {
			return;
		}

		$this->updateCurrentGlobalsCache();
		$this->touchCachedLocale( $new_locale );
		$this->changeWpLocale( $new_locale );
		$this->changeMoObjects( $new_locale );
		$this->setCurrentLocale( $new_locale );
	}

	public static function resetCache( $locale = null ) {
		self::$current_locale                 = $locale;
		self::$base_locale                    = $locale;
		self::$locale_stack                   = [];
		self::$globals_cache                  = [];
		self::$globals_cache_bytes            = [];
		self::$file_size_cache                = [];
		self::$initial_locale                 = $locale;
		self::$pending_queue_processing_depth = 0;
	}

	public static function beginPendingQueueProcessing() {
		self::$pending_queue_processing_depth++;
		self::trimCacheToLimit( 1 );
	}

	public static function endPendingQueueProcessing() {
		self::$pending_queue_processing_depth = max( 0, self::$pending_queue_processing_depth - 1 );
	}

	public static function isPendingQueueProcessing() {
		return self::$pending_queue_processing_depth > 0;
	}

	public static function withPendingQueueProcessing( callable $callback ) {
		self::beginPendingQueueProcessing();

		try {
			return $callback();
		} finally {
			self::endPendingQueueProcessing();
		}
	}

	public static function releasePendingQueueLocale( $locale, array $domains = [] ) {
		$locale = (string) $locale;
		if (
			! self::isPendingQueueProcessing()
			|| $locale === ''
			|| $locale === self::$current_locale
		) {
			return false;
		}

		if ( isset( self::$globals_cache[ $locale ]['l10n'] ) ) {
			$domains = array_merge( $domains, array_keys( self::$globals_cache[ $locale ]['l10n'] ) );
		}

		self::unloadDomainsFromTranslationController( $locale, $domains );
		unset( self::$globals_cache[ $locale ] );
		unset( self::$globals_cache_bytes[ $locale ] );

		return true;
	}

	private function updateCurrentGlobalsCache() {
		$cache = [
			'wp_locale' => isset( $GLOBALS['wp_locale'] ) ? $GLOBALS['wp_locale'] : null,
			'l10n'      => isset( $GLOBALS['l10n'] ) ? (array) $GLOBALS['l10n'] : [],
		];

		unset( self::$globals_cache[ $this->getCurrentLocale() ] );
		self::$globals_cache[ $this->getCurrentLocale() ]       = $cache;
		self::$globals_cache_bytes[ $this->getCurrentLocale() ] = self::estimateRetainedBytes(
			$this->getCurrentLocale(),
			$cache['l10n']
		);

		$this->enforceCacheLimit();
	}

	private function touchCachedLocale( $locale ) {
		if ( isset( self::$globals_cache[ $locale ] ) ) {
			$entry = self::$globals_cache[ $locale ];
			unset( self::$globals_cache[ $locale ] );
			self::$globals_cache[ $locale ] = $entry;
		}
	}

	private function enforceCacheLimit() {
		if ( self::isPendingQueueProcessing() ) {
			self::trimCacheToLimit( 1 );
			return;
		}

		$memoryLimit = self::getMemoryLimitBytes();
		if ( $memoryLimit > 0 ) {
			$availableBytes = $memoryLimit - memory_get_usage( true );
			if ( $availableBytes <= self::getValveReserveBytes( $memoryLimit ) ) {
				self::trimCacheToLimit( 1 );
				return;
			}
		}

		$limit = max( 1, (int) apply_filters( 'wpml_st_max_cached_locale_dictionaries', self::MAX_CACHED_LOCALES ) );
		self::trimCacheToLimit( $limit );

		self::trimCacheToBudget( self::getRetentionBudgetBytes() );
	}

	private static function trimCacheToLimit( $limit ) {
		foreach ( array_reverse( array_keys( self::$globals_cache ) ) as $locale ) {
			if ( count( self::$globals_cache ) <= $limit ) {
				break;
			}
			if ( $locale === self::$initial_locale || $locale === self::$current_locale ) {
				continue;
			}
			self::evictLocale( $locale );
		}
	}

	private static function trimCacheToBudget( $budgetBytes ) {
		if ( array_sum( self::$globals_cache_bytes ) <= $budgetBytes ) {
			return;
		}

		foreach ( array_reverse( array_keys( self::$globals_cache ) ) as $locale ) {
			if ( array_sum( self::$globals_cache_bytes ) <= $budgetBytes ) {
				break;
			}
			if ( $locale === self::$initial_locale || $locale === self::$current_locale ) {
				continue;
			}
			self::evictLocale( $locale );
		}
	}

	private static function evictLocale( $locale ) {
		self::unloadFromTranslationController( $locale );
		unset( self::$globals_cache[ $locale ] );
		unset( self::$globals_cache_bytes[ $locale ] );
	}

	private static function getRetentionBudgetBytes() {
		$memoryLimit = self::getMemoryLimitBytes();

		if ( $memoryLimit <= 0 ) {
			$budget = self::CACHE_BUDGET_MAX_BYTES;
		} else {
			$budget = $memoryLimit - self::getReserveBytes( $memoryLimit );
		}

		$budget = self::clampBudgetBytes( $budget );

		$filtered = apply_filters( 'wpml_st_locale_dictionary_cache_budget_bytes', $budget, $memoryLimit );

		return self::clampBudgetBytes( (int) $filtered );
	}

	private static function clampBudgetBytes( $budget ) {
		return (int) max( self::CACHE_BUDGET_MIN_BYTES, min( self::CACHE_BUDGET_MAX_BYTES, $budget ) );
	}

	private static function getMemoryLimitBytes() {
		$limit = (string) ini_get( 'memory_limit' );

		if ( function_exists( 'wp_convert_hr_to_bytes' ) ) {
			return (int) wp_convert_hr_to_bytes( $limit );
		}

		$value = (int) $limit;
		switch ( strtolower( substr( trim( $limit ), -1 ) ) ) {
			case 'g':
				return $value * 1073741824;
			case 'm':
				return $value * 1048576;
			case 'k':
				return $value * 1024;
		}

		return $value;
	}

	private static function getReserveBytes( $memoryLimit ) {
		return (int) max( self::CACHE_RESERVE_MIN_BYTES, $memoryLimit * self::CACHE_RESERVE_RATIO );
	}

	private static function getValveReserveBytes( $memoryLimit ) {
		return (int) min(
			self::CACHE_VALVE_RESERVE_MAX_BYTES,
			$memoryLimit * self::CACHE_VALVE_RESERVE_RATIO
		);
	}

	private static function estimateRetainedBytes( $locale, array $l10n ) {
		$bytes = self::SNAPSHOT_BASE_BYTES + count( $l10n ) * self::SNAPSHOT_PER_DOMAIN_BYTES;

		if ( class_exists( \WP_Translation_Controller::class ) ) {
			try {
				$controller = \WP_Translation_Controller::get_instance();
				if ( null === self::$loaded_translations_property ) {
					self::$loaded_translations_property = new \ReflectionProperty( $controller, 'loaded_translations' );
					if ( PHP_VERSION_ID < 80100 ) {
						self::$loaded_translations_property->setAccessible( true );
					}
				}
				$loaded = self::$loaded_translations_property->getValue( $controller );
				$seen   = [];

				if ( isset( $loaded[ $locale ] ) && is_array( $loaded[ $locale ] ) ) {
					foreach ( $loaded[ $locale ] as $files ) {
						foreach ( (array) $files as $file ) {
							if ( ! $file instanceof \WP_Translation_File ) {
								continue;
							}
							$path = $file->get_file();
							if ( isset( $seen[ $path ] ) ) {
								continue;
							}
							$seen[ $path ] = true;
							if ( ! isset( self::$file_size_cache[ $path ] ) ) {
								self::$file_size_cache[ $path ] = (int) @filesize( $path );
							}
							$bytes += (int) ( self::$file_size_cache[ $path ] * self::CATALOG_EXPANSION_FACTOR );
						}
					}
				}
			} catch ( \Throwable $e ) {
			}
		}

		foreach ( $l10n as $translations ) {
			if ( $translations instanceof \MO && isset( $translations->entries ) && is_array( $translations->entries ) ) {
				$bytes += count( $translations->entries ) * self::LEGACY_ENTRY_BYTES;
			}
		}

		return (int) $bytes;
	}

	public static function getCacheDiagnostics() {
		return [
			'locales'      => self::$globals_cache_bytes,
			'total_bytes'  => (int) array_sum( self::$globals_cache_bytes ),
			'budget_bytes' => self::getRetentionBudgetBytes(),
		];
	}

	private static function unloadFromTranslationController( $locale ) {
		if ( empty( self::$globals_cache[ $locale ]['l10n'] ) ) {
			return;
		}

		self::unloadDomainsFromTranslationController(
			$locale,
			array_keys( self::$globals_cache[ $locale ]['l10n'] )
		);
	}

	private static function unloadDomainsFromTranslationController( $locale, array $domains ) {
		if ( ! class_exists( \WP_Translation_Controller::class ) ) {
			return;
		}

		$controller = \WP_Translation_Controller::get_instance();
		foreach ( array_unique( array_map( 'strval', $domains ) ) as $domain ) {
			if ( $domain !== '' ) {
				$controller->unload_textdomain( $domain, $locale );
			}
		}
	}

	private function changeWpLocale( $new_locale ) {
		if ( isset( self::$globals_cache[ $new_locale ]['wp_locale'] ) ) {
			$GLOBALS['wp_locale'] = self::$globals_cache[ $new_locale ]['wp_locale'];
		} else {
			$GLOBALS['wp_locale'] = new WPML_Locale();
		}
	}

	private function changeMoObjects( $new_locale ) {
		$this->resetTranslationAvailabilityInformation();

		$cachedMoObjects = isset( self::$globals_cache[ $new_locale ]['l10n'] )
			? self::$globals_cache[ $new_locale ]['l10n']
			: [];

		$GLOBALS['l10n'] = $this->jit_mo_factory->get( $new_locale, $this->getUnloadedDomains(), $cachedMoObjects );

		$this->setLocaleInWP65TranslationController( $new_locale );
	}

	private function setLocaleInWP65TranslationController( $new_locale ) {
		if ( class_exists( \WP_Translation_Controller::class ) ) {
			\WP_Translation_Controller::get_instance()->set_locale( $new_locale );
		}
	}

	private function resetTranslationAvailabilityInformation() {
		global $wp_textdomain_registry;
		if ( ! isset( $wp_textdomain_registry ) && function_exists( '_get_path_to_translation' ) ) {
			_get_path_to_translation( '', true );
		}
	}

	public function filterLocale( $locale ) {
		$currentLocale = $this->getCurrentLocale();

		if ( ! $currentLocale ) {
			return $locale;
		}

		if ( ! self::$locale_stack && $currentLocale === self::$base_locale ) {
			return $locale;
		}

		return $currentLocale;
	}

	private function getUnloadedDomains() {
		$unloadedDomains = isset( $GLOBALS['l10n_unloaded'] ) ? array_keys( (array) $GLOBALS['l10n_unloaded'] ) : [];

		if ( class_exists('\WP_Translation_Controller') ) {
			foreach ( $unloadedDomains as $key => $domain ) {
				if ( isset( $GLOBALS['l10n'][ $domain ] ) && ! $GLOBALS['l10n'][ $domain ] instanceof \NOOP_Translations ) {
					unset( $unloadedDomains[ $key ] );
				}
			}
		}
		return $unloadedDomains;
	}
}
