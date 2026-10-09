<?php

namespace WPML\StringTranslation\Infrastructure\StringCore\Command;

use WPML\StringTranslation\Application\StringCore\Domain\StringItem;
use WPML\StringTranslation\Application\StringCore\Command\LoadExistingStringTranslationsCommandInterface;
use WPML\StringTranslation\Application\StringCore\Command\InsertStringTranslationsCommandInterface;
use WPML\StringTranslation\Application\StringCore\Repository\TranslationsRepositoryInterface;
use WPML\StringTranslation\Application\Setting\Repository\SettingsRepositoryInterface;
use WPML\StringTranslation\Application\StringCore\Command\UpdateStringsCommandInterface;

class LoadExistingStringTranslationsCommand implements LoadExistingStringTranslationsCommandInterface
{
	private $translationsRepository;

	private $settingsRepository;

	private $insertStringTranslations;

	private $updateStringsCommand;

	public function __construct(
		TranslationsRepositoryInterface          $translationsRepository,
		SettingsRepositoryInterface              $settingsRepository,
		InsertStringTranslationsCommandInterface $insertStringTranslations,
		UpdateStringsCommandInterface            $updateStringsCommand
	) {
		$this->translationsRepository = $translationsRepository;
		$this->settingsRepository = $settingsRepository;
		$this->insertStringTranslations = $insertStringTranslations;
		$this->updateStringsCommand = $updateStringsCommand;
	}

	public function run( array $strings ) {
		$translations = $this->translationsRepository->createEntitiesForExistingTranslations( $strings );
		$this->insertStringTranslations->run( $translations );
		$this->updateStringTranslationStatuses( $strings );
	}

	private function updateStringTranslationStatuses( array $strings ) {
		$allLanguageCodes = $this->settingsRepository->getActiveSecondaryLanguageCodes();
		$recomputed       = [];
		foreach ( $strings as $string ) {
			if ( $string->refreshStatus( $this->settingsRepository->getDefaultLanguageCode(), $allLanguageCodes ) ) {
				$recomputed[] = $string;
			}
		}

		$stringsByStatus = [];
		foreach ( $recomputed as $string ) {
			if ( ! array_key_exists( $string->getStatus(), $stringsByStatus ) ) {
				$stringsByStatus[ $string->getStatus() ] = [];
			}

			$stringsByStatus[ $string->getStatus() ][] = $string;
		}

		foreach ( $stringsByStatus as $status => $strings ) {
			$this->updateStringsCommand->run( $strings, ['status'], [ $status ] );
		}
	}
}