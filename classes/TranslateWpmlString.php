<?php

namespace WPML\ST;

use WPML\ST\MO\Hooks\LanguageSwitch;
use WPML\ST\MO\File\Manager;
use WPML\ST\StringsFilter\Provider;
use WPML_Locale;
use WPML_Displayed_String_Filter;

class TranslateWpmlString {

	private static $loadedDomains = [];

	private $filterProvider;

	private $languageSwitch;

	private $locale;

	private $fileManager;

	private $lock = false;

	public function __construct(
		Provider $filterProvider,
		LanguageSwitch $languageSwitch,
		WPML_Locale $locale,
		Manager $fileManager
	) {
		$this->filterProvider = $filterProvider;
		$this->languageSwitch = $languageSwitch;
		$this->locale         = $locale;
		$this->fileManager    = $fileManager;
	}

	public function init() {
		$this->languageSwitch->initCurrentLocale();
	}

	public function translate( $wpmlContext, $name, $value = false, $allowEmptyValue = false, &$hasTranslation = null, $targetLang = null ) {
		if ( $this->lock ) {
			return $value;
		}

		$this->lock = true;

		if ( wpml_st_is_requested_blog() ) {

			if ( self::canTranslateWithMO( $value, $name ) ) {
				$value = $this->translateByMOFile( $wpmlContext, $name, $value, $hasTranslation, $targetLang );
			} else {
				$value = $this->translateByDBQuery( $wpmlContext, $name, $value, $hasTranslation, $targetLang );
			}
		}

		$this->lock = false;

		return $value;
	}

	private function translateByMOFile( $wpmlContext, $name, $value, &$hasTranslation, $targetLang ) {
		if ( $targetLang && ! $this->locale->get_locale( $targetLang ) ) {
			return $this->translateByDBQuery( $wpmlContext, $name, $value, $hasTranslation, $targetLang );
		}

		list ( $domain, $gettextContext ) = wpml_st_extract_context_parameters( $wpmlContext );
		$normalizedName                   = WPML_Displayed_String_Filter::truncate_long_string( $name );

		$translateByName = function ( $locale ) use ( $normalizedName, $name, $domain, $gettextContext ) {
			$this->loadTextDomain( $domain, $locale );

			if ( $gettextContext ) {
				$result = _x( $normalizedName, $gettextContext, $domain );
			} else {
				$result = __( $normalizedName, $domain );
			}

			if ( $result === $normalizedName && $normalizedName !== $name ) {
				$fallback = $gettextContext
					? _x( $name, $gettextContext, $domain )
					: __( $name, $domain );
				if ( $fallback !== $name ) {
					return $fallback;
				}
			}

			return $result;
		};

		do_action( 'wpml_st_update_settings', 'disableAutoregistration' );
		$new_value      = $this->withMOLocale( $targetLang, $translateByName );
		$hasTranslation = $new_value !== $normalizedName;
		if ( $hasTranslation ) {
			$value = $new_value;
		} else {
			if ( $normalizedName !== $name ) {
				$value = $this->translateByDBQuery( $wpmlContext, $name, $value, $hasTranslation, $targetLang );
			} else {
				do_action( 'wpml_st_add_to_queue', $value, $domain, $gettextContext, $name );
			}
		}
		do_action( 'wpml_st_update_settings', 'enableAutoregistration' );

		return $value;
	}

	private function translateByDBQuery( $wpmlContext, $name, $value, &$hasTranslation, $targetLang ) {
		$filter = $this->filterProvider->getFilter( $targetLang, $name );

		if ( $filter ) {
			$value = $filter->translate_by_name_and_context( $value, $name, $wpmlContext, $hasTranslation );
		} else {
			$hasTranslation = false;
		}

		return $value;
	}

	private function loadTextDomain( $domain, $locale ) {
		if (
			! isset( $GLOBALS['l10n'][ $domain ] )
			&& ! isset( $GLOBALS['l10n_unloaded'][ $domain ] )
			&& ! isset( self::$loadedDomains[ $locale ][ $domain ] )
		) {
			load_textdomain(
				$domain,
				$this->fileManager->getFilepath( $domain, $locale ),
				$locale
			);

			self::$loadedDomains[ $locale ][ $domain ] = true;
		}
	}

	private function withMOLocale( $targetLang, $function ) {
		$initialLocale = $this->languageSwitch->getCurrentLocale();

		$targetLocale = $targetLang ? $this->locale->get_locale( $targetLang ) : false;

		if ( $targetLocale ) {
			$this->languageSwitch->switchToLocale( $targetLocale );
			$result = $function( $targetLocale );
			$this->languageSwitch->switchToLocale( $initialLocale );
		} else {
			$result = $function( $initialLocale );
		}

		return $result;
	}

	public static function canTranslateWithMO( $original, $name ) {
		return $original && self::isWpmlRegisteredString( $original, $name );
	}

	private static function isWpmlRegisteredString( $original, $name ) {
		return $name && md5( (string) $original ) !== $name;
	}

	public static function resetCache() {
		self::$loadedDomains = [];
	}
}
