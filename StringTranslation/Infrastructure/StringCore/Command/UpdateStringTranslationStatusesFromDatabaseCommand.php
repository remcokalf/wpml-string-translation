<?php

namespace WPML\StringTranslation\Infrastructure\StringCore\Command;

use WPML\Core\SharedKernel\Component\Language\Domain\LanguageCode;
use WPML\StringTranslation\Application\Setting\Repository\SettingsRepositoryInterface;
use WPML\StringTranslation\Application\StringCore\Command\UpdateStringTranslationStatusesFromDatabaseCommandInterface;
use WPML\StringTranslation\Application\StringCore\Domain\StringItem;

class UpdateStringTranslationStatusesFromDatabaseCommand implements UpdateStringTranslationStatusesFromDatabaseCommandInterface {

	const CHUNK_SIZE = 1000;

	private $wpdb;

	private $settingsRepository;

	public function __construct( $wpdb, SettingsRepositoryInterface $settingsRepository ) {
		$this->wpdb               = $wpdb;
		$this->settingsRepository = $settingsRepository;
	}

	public function run( array $strings ) {
		$stringsById = [];
		foreach ( $strings as $string ) {
			if ( $string->hasId() ) {
				$stringsById[ (int) $string->getId() ] = $string;
			}
		}

		if ( count( $stringsById ) === 0 ) {
			return;
		}

		$translatedLanguagesByString = $this->getCompletedTranslationLanguages( array_keys( $stringsById ) );
		$stringsByStatus             = [];

		foreach ( $stringsById as $stringId => $string ) {
			$targetLanguages     = $this->getTargetLanguages( $string );
			$translatedLanguages = isset( $translatedLanguagesByString[ $stringId ] )
				? $translatedLanguagesByString[ $stringId ]
				: [];

			if ( count( $targetLanguages ) === 0 ) {
				continue;
			}

			$translatedCount = count( array_intersect( $targetLanguages, $translatedLanguages ) );
			if ( 0 === $translatedCount ) {
				$status = count( $translatedLanguages ) > 0
					? ICL_STRING_TRANSLATION_PARTIAL
					: ICL_STRING_TRANSLATION_NOT_TRANSLATED;
			} elseif ( $translatedCount < count( $targetLanguages ) ) {
				$status = ICL_STRING_TRANSLATION_PARTIAL;
			} else {
				$status = ICL_STRING_TRANSLATION_COMPLETE;
			}

			$string->setStatus( $status );
			if ( ! isset( $stringsByStatus[ $status ] ) ) {
				$stringsByStatus[ $status ] = [];
			}
			$stringsByStatus[ $status ][] = $stringId;
		}

		foreach ( $stringsByStatus as $status => $stringIds ) {
			foreach ( array_chunk( $stringIds, self::CHUNK_SIZE ) as $chunk ) {
				$query = "UPDATE {$this->wpdb->prefix}icl_strings SET status = " . (int) $status
					. ' WHERE id IN (' . implode( ',', array_map( 'intval', $chunk ) ) . ')';
				$this->runCheckedQuery( $query );
			}
		}
	}

	private function getCompletedTranslationLanguages( array $stringIds ) : array {
		$languagesByString = [];

		foreach ( array_chunk( $stringIds, self::CHUNK_SIZE ) as $chunk ) {
			$query = 'SELECT string_id, language'
				. " FROM {$this->wpdb->prefix}icl_string_translations"
				. ' WHERE string_id IN (' . implode( ',', array_map( 'intval', $chunk ) ) . ')'
				. ' AND (status = ' . (int) ICL_TM_COMPLETE
				. " OR (mo_string IS NOT NULL AND mo_string != ''))";
			$rows  = $this->runCheckedSelect( $query );

			foreach ( $rows as $row ) {
				$stringId = (int) ( is_array( $row ) ? $row['string_id'] : $row->string_id );
				$language = (string) ( is_array( $row ) ? $row['language'] : $row->language );
				if ( ! isset( $languagesByString[ $stringId ] ) ) {
					$languagesByString[ $stringId ] = [];
				}
				$languagesByString[ $stringId ][] = $language;
			}
		}

		foreach ( $languagesByString as $stringId => $languages ) {
			$languagesByString[ $stringId ] = array_values( array_unique( $languages ) );
		}

		return $languagesByString;
	}

	private function getTargetLanguages( StringItem $string ) : array {
		$languages       = $this->settingsRepository->getActiveSecondaryLanguageCodes();
		$defaultLanguage = $this->settingsRepository->getDefaultLanguageCode();

		if ( ! LanguageCode::isEnglish( $defaultLanguage ) && $string->getLanguage() !== $defaultLanguage ) {
			$languages[] = $defaultLanguage;
		}

		$languages = array_filter(
			array_unique( $languages ),
			function( $language ) use ( $string ) {
				return $language !== $string->getLanguage();
			}
		);

		return array_values( $languages );
	}

	private function runCheckedSelect( string $query ) : array {
		$previousSuppressErrors = $this->wpdb->suppress_errors( true );
		try {
			$rows          = $this->wpdb->get_results( $query );
			$databaseError = isset( $this->wpdb->last_error ) ? (string) $this->wpdb->last_error : '';
		} finally {
			$this->wpdb->suppress_errors( $previousSuppressErrors );
		}

		if ( '' !== $databaseError || ! is_array( $rows ) ) {
			throw new \RuntimeException(
				'' !== $databaseError ? $databaseError : $this->getDatabaseErrorMessage()
			);
		}

		return $rows;
	}

	private function runCheckedQuery( string $query ) {
		$previousSuppressErrors = $this->wpdb->suppress_errors( true );
		try {
			$result = $this->wpdb->query( $query );
		} finally {
			$this->wpdb->suppress_errors( $previousSuppressErrors );
		}

		if ( false === $result ) {
			throw new \RuntimeException( $this->getDatabaseErrorMessage() );
		}
	}

	private function getDatabaseErrorMessage() : string {
		return isset( $this->wpdb->last_error ) && strlen( (string) $this->wpdb->last_error ) > 0
			? (string) $this->wpdb->last_error
			: 'String Translation database operation failed.';
	}
}
