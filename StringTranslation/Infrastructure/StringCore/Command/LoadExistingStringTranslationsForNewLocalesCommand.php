<?php

namespace WPML\StringTranslation\Infrastructure\StringCore\Command;

use WPML\StringTranslation\Application\Setting\Repository\SettingsRepositoryInterface;
use WPML\StringTranslation\Application\StringCore\Command\LoadExistingStringTranslationsForLocaleCommandInterface;
use WPML\StringTranslation\Application\StringCore\Command\LoadExistingStringTranslationsForNewLocalesCommandInterface;
use WPML\StringTranslation\Application\StringCore\Domain\StringItem;
use WPML\StringTranslation\Application\StringCore\Query\Criteria\SearchCriteria;
use WPML\StringTranslation\Application\StringCore\Query\Criteria\SearchSelectCriteria;
use WPML\StringTranslation\Application\StringCore\Query\FindAllStringsCountQueryInterface;
use WPML\StringTranslation\Application\StringCore\Query\FindAllStringsQueryInterface;

class LoadExistingStringTranslationsForNewLocalesCommand implements LoadExistingStringTranslationsForNewLocalesCommandInterface {

	const BATCH_SIZE       = 1000;
	const HARVESTED_OPTION = 'wpml_st_harvested_locales';

	private $settingsRepository;

	private $loadExistingStringTranslationsForLocaleCommand;

	private $findAllStringsQuery;

	private $findAllStringsCountQuery;

	public function __construct(
		SettingsRepositoryInterface                            $settingsRepository,
		LoadExistingStringTranslationsForLocaleCommandInterface $loadExistingStringTranslationsForLocaleCommand,
		FindAllStringsQueryInterface                          $findAllStringsQuery,
		FindAllStringsCountQueryInterface                     $findAllStringsCountQuery
	) {
		$this->settingsRepository                             = $settingsRepository;
		$this->loadExistingStringTranslationsForLocaleCommand = $loadExistingStringTranslationsForLocaleCommand;
		$this->findAllStringsQuery                           = $findAllStringsQuery;
		$this->findAllStringsCountQuery                      = $findAllStringsCountQuery;
	}

	public function run() {
		$activePairs   = $this->settingsRepository->getStringHarvestLanguageLocalePairs();
		$activeLocales = array_values(
			array_map(
				function ( $pair ) {
					return $pair['locale'];
				},
				$activePairs
			)
		);

		$harvested = $this->getHarvestedLocales();

		$newPairs = array_values(
			array_filter(
				$activePairs,
				function ( $pair ) use ( $harvested ) {
					return ! in_array( $pair['locale'], $harvested, true );
				}
			)
		);

		if ( ! $newPairs ) {
			if ( $harvested !== $activeLocales ) {
				$this->setHarvestedLocales( $activeLocales );
			}

			return;
		}

		$totalStringsCount = $this->findAllStringsCountQuery->execute( new SearchCriteria() );

		for ( $offset = 0; $offset < $totalStringsCount; $offset += self::BATCH_SIZE ) {
			$criteria = new SearchCriteria(
				null,
				null,
				null,
				null,
				null,
				null,
				null,
				null,
				[],
				self::BATCH_SIZE,
				$offset
			);

			$strings = $this->createStrings(
				$this->findAllStringsQuery->execute( $criteria, new SearchSelectCriteria( [ 'id', 'gettext_context', 'context', 'value' ] ) )
			);

			foreach ( $newPairs as $pair ) {
				$this->loadExistingStringTranslationsForLocaleCommand->run( $strings, $pair['locale'], $pair['languageCode'] );
			}

			unset( $strings );
		}

		$this->setHarvestedLocales( $activeLocales );
	}

	protected function getHarvestedLocales() {
		return array_values( array_map( 'strval', (array) get_option( self::HARVESTED_OPTION, [] ) ) );
	}

	protected function setHarvestedLocales( array $locales ) {
		update_option( self::HARVESTED_OPTION, array_values( $locales ), false );
	}

	private function createStrings( array $strings ) {
		return array_map(
			function ( $string ) {
				$item = new StringItem(
					'en',
					$string->getDomain(),
					$string->getContext(),
					$string->getValue()
				);
				$item->setId( $string->getId() );

				return $item;
			},
			$strings
		);
	}
}
