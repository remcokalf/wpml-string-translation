<?php

namespace WPML\StringTranslation\Application\StringCore\Repository;

use WPML\StringTranslation\Application\StringCore\Domain\StringItem;
use WPML\StringTranslation\Application\StringCore\Domain\StringTranslation;

interface TranslationsRepositoryInterface {
	public function isTranslationAvailable( string $text, string $domain, ?string $context = null ): bool;
	public function createEntitiesForExistingTranslations( array $strings );

	public function createEntitiesForExistingTranslationsForLocale( array $strings, string $locale, string $languageCode );

	public function getTranslationFilepathsForLocale( array $strings, string $locale ): array;
}
