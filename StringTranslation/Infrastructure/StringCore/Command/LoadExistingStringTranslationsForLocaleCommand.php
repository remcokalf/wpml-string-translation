<?php

namespace WPML\StringTranslation\Infrastructure\StringCore\Command;

use WPML\StringTranslation\Application\StringCore\Command\InsertStringTranslationsGuardedCommandInterface;
use WPML\StringTranslation\Application\StringCore\Command\LoadExistingStringTranslationsForLocaleCommandInterface;
use WPML\StringTranslation\Application\StringCore\Domain\StringItem;
use WPML\StringTranslation\Application\StringCore\Repository\TranslationsRepositoryInterface;

class LoadExistingStringTranslationsForLocaleCommand implements LoadExistingStringTranslationsForLocaleCommandInterface {

	private $translationsRepository;

	private $insertStringTranslations;

	public function __construct(
		TranslationsRepositoryInterface $translationsRepository,
		InsertStringTranslationsGuardedCommandInterface $insertStringTranslations
	) {
		$this->translationsRepository   = $translationsRepository;
		$this->insertStringTranslations = $insertStringTranslations;
	}

	public function run( array $strings, string $locale, string $languageCode ) : int {
		$translations = $this->translationsRepository->createEntitiesForExistingTranslationsForLocale(
			$strings,
			$locale,
			$languageCode
		);

		$translationsCount = $this->insertStringTranslations->run( $translations );
		unset( $translations );

		return $translationsCount;
	}
}
