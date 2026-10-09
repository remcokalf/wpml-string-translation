<?php

namespace WPML\StringTranslation\Application\StringCore\Command;

use WPML\StringTranslation\Application\StringCore\Domain\StringItem;

interface LoadExistingStringTranslationsForLocaleCommandInterface {
	public function run( array $strings, string $locale, string $languageCode ): int;
}
