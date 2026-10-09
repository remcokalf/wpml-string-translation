<?php

namespace WPML\StringTranslation\Infrastructure\StringCore\Repository;

use WPML\StringTranslation\Application\StringCore\Repository\TranslationsRepositoryInterface;
use WPML\StringTranslation\Application\Setting\Repository\SettingsRepositoryInterface;
use WPML\StringTranslation\Application\StringCore\Domain\StringItem;
use WPML\StringTranslation\Application\StringCore\Domain\StringTranslation;

class TranslationsRepository implements TranslationsRepositoryInterface {

	private $settingsRepository;

	public function __construct( SettingsRepositoryInterface $settingsRepository ) {
		$this->settingsRepository = $settingsRepository;
	}

	public function isTranslationAvailable( string $text, string $domain, ?string $context = null ): bool {
		global $l10n;
		$translations = get_translations_for_domain( $domain );

		if ( class_exists('\WP_Translation_Controller') || method_exists( $translations, 'translate' ) ) {
			$translation = $translations->translate( $text, $context );
			return $translation !== $text;
		} else {
			$entry = new \Translation_Entry(
				array(
					'singular' => $text,
					'context' => $context,
				)
			);

			$translated = $translations->translate_entry($entry);
			return $translated && !empty($translated->translations);
		}
	}

	private function getTranslatedStringText( $translations, string $text, ?string $context = null ) {
		if ( class_exists('\WP_Translation_Controller') || method_exists( $translations, 'translate' ) ) {
			$translation = $translations->translate( $text, $context );
			return ( $translation === $text ) ? null : $translation;
		} else {
			$entry = new \Translation_Entry(
				array(
					'singular' => $text,
					'context' => $context,
				)
			);

			$translated = $translations->translate_entry($entry);
			if (!$translated || empty($translated->translations)) {
				return null;
			}

			return $translated->translations[0];
		}
	}

	public function createEntitiesForExistingTranslations( array $strings ) {
		if ( count( $strings ) === 0 ) {
			return [];
		}

		$stringTranslations = [];

		foreach ( $this->settingsRepository->getStringHarvestLanguageLocalePairs() as $pair ) {
			if (
				! isset( $pair['languageCode'], $pair['locale'] )
				|| ! is_string( $pair['languageCode'] )
				|| ! is_string( $pair['locale'] )
			) {
				continue;
			}

			$stringTranslations = array_merge(
				$stringTranslations,
				$this->createEntitiesForExistingTranslationsForLocaleInternal(
					$strings,
					$pair['locale'],
					true,
					$pair['languageCode']
				)
			);
		}

		return $stringTranslations;
	}

	public function createEntitiesForExistingTranslationsForLocale( array $strings, string $locale, string $languageCode ) {
		return $this->createEntitiesForExistingTranslationsForLocaleInternal(
			$strings,
			$locale,
			false,
			$languageCode
		);
	}

	private function createEntitiesForExistingTranslationsForLocaleInternal(
		array $strings,
		string $locale,
		bool $attachTranslations,
		string $languageCode
	) {
		if ( count( $strings ) === 0 || '' === $languageCode ) {
			return [];
		}

		$domains = $this->getDomains( $strings );
		$this->settingsRepository->switchToLocale( $locale, $domains, $languageCode );

		try {
			if ( \WPML\LIB\WP\WordPress::versionCompare( '<', '6.2.000' ) && determine_locale() !== $locale ) {
				return [];
			}

			load_default_textdomain( $locale );
			foreach ( $this->getTranslationFilepathsByDomainForLocale( $strings, $locale ) as $domain => $filepaths ) {
				foreach ( $filepaths as $filepath ) {
					load_textdomain( $domain, $filepath, $locale );
				}
			}

			return $this->getTranslations( $strings, $languageCode, $attachTranslations );
		} finally {
			$this->settingsRepository->restorePreviousLocale();
		}
	}

	public function getTranslationFilepathsForLocale( array $strings, string $locale ): array {
		$filepaths = [];
		foreach ( $this->getTranslationFilepathsByDomainForLocale( $strings, $locale ) as $domainFilepaths ) {
			$filepaths = array_merge( $filepaths, $domainFilepaths );
		}

		return array_values( array_unique( $filepaths ) );
	}

	private function getDomains( array $strings ): array {
		return array_values(
			array_unique(
				array_map(
					function( $string ) {
						return $string->getDomain();
					},
					$strings
				)
			)
		);
	}

	private function getTranslationFilepathsByDomainForLocale( array $strings, string $locale ): array {
		$filepathsByDomain = [];
		foreach ( $this->getDomains( $strings ) as $domain ) {
			$filteredFilepaths = apply_filters( 'wpml_st_get_filepathes_for_translation_files', $domain, $locale );
			$filepaths         = [];

			if ( is_array( $filteredFilepaths ) || $filteredFilepaths instanceof \Traversable ) {
				foreach ( $filteredFilepaths as $filepath ) {
					if ( is_string( $filepath ) && strlen( $filepath ) > 0 ) {
						$filepaths[] = $filepath;
					}
				}
			}

			$filepathsByDomain[ $domain ] = array_values( array_unique( $filepaths ) );
		}

		return $filepathsByDomain;
	}

	private function getTranslations( array $strings, string $language, bool $attachTranslations = true ): array {
		$stringTranslations   = [];
		$translationsByDomain = [];

		foreach ( $strings as $string ) {
			if ( ! in_array( $string->getDomain(), array_keys( $translationsByDomain ) ) ) {
				$translations = get_translations_for_domain( $string->getDomain() );
				$translationsByDomain[ $string->getDomain() ] = $translations;
			}

			$translation = $this->getTranslatedStringText(
				$translationsByDomain[ $string->getDomain() ],
				$string->getValue(),
				$string->getContext()
			);
			if ( $translation ) {
				$stringTranslation = new StringTranslation(
					$language,
					$translation,
					$string
				);

				$stringTranslations[] = $stringTranslation;
				if ( $attachTranslations ) {
					$string->addTranslation( $stringTranslation );
				}
			}
		}

		return $stringTranslations;
	}
}
