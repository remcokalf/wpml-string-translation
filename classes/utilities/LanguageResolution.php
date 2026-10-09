<?php

namespace WPML\ST\Utils;

use SitePress;
use WPML\StringTranslation\Infrastructure\TranslateEverything\EnglishSourceLanguage;
use WPML_String_Translation;

class LanguageResolution {

	private $sitepress;

	private $string_translation;

	private $admin_language;

	public function __construct( SitePress $sitepress, WPML_String_Translation $string_translation ) {
		$this->sitepress          = $sitepress;
		$this->string_translation = $string_translation;
	}

	public function getCurrentLanguage() {
		if ( $this->string_translation->should_use_admin_language() ) {
			$current_lang = $this->getAdminLanguage();
		} else {
			$current_lang = $this->sitepress->get_current_language();
		}

		return $this->withFallbacks( $current_lang );
	}

	public function getCurrentLocale() {
		$current_language = $this->getCurrentLanguage();
		$wpml_locale      = $this->sitepress->get_locale( $current_language );
		$admin_locale     = $this->getWordPressAdminLocale( $wpml_locale );

		return null !== $admin_locale ? $admin_locale : $wpml_locale;
	}

	private function getWordPressAdminLocale( $wpml_locale ) {
		if (
			! $this->string_translation->should_use_admin_language()
			|| $this->sitepress->is_wpml_switch_language_triggered()
		) {
			return null;
		}

		$wordpress_locale = get_user_locale();

		return $this->localesUseSameLanguage( $wordpress_locale, $wpml_locale )
			? $wordpress_locale
			: null;
	}

	private function localesUseSameLanguage( $first_locale, $second_locale ) {
		$first_language = $this->languageFromLocale( $first_locale );

		return $first_language
			&& $first_language === $this->languageFromLocale( $second_locale );
	}

	private function languageFromLocale( $locale ) {
		$parts = preg_split( '/[_-]/', (string) $locale );

		return strtolower( $parts[0] );
	}

	public function getLanguageFor( $language ) {
		if ( $this->string_translation->should_use_admin_language() ) {
			$language = $this->getAdminLanguage();
		}

		return $this->withFallbacks( $language );
	}

	public function getLocaleFor( $language ) {
		return $this->sitepress->get_locale( $this->getLanguageFor( $language ) );
	}

	private function withFallbacks( $language ) {
		if ( ! $language ) {
			$language = $this->sitepress->get_default_language();
			if ( ! $language ) {
				$language = EnglishSourceLanguage::resolve(
					array_keys( (array) $this->sitepress->get_active_languages() ),
					''
				);
			}
		}

		return $language;
	}

	private function getAdminLanguage() {
		if ( $this->sitepress->is_wpml_switch_language_triggered() ) {
			return $this->sitepress->get_admin_language();
		}

		if ( ! $this->admin_language ) {
			$this->admin_language = $this->sitepress->get_admin_language();
		}

		return $this->admin_language;
	}
}
