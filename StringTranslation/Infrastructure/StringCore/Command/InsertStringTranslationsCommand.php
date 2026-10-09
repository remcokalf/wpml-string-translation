<?php

namespace WPML\StringTranslation\Infrastructure\StringCore\Command;

use WPML\StringTranslation\Application\StringCore\Domain\StringTranslation;
use WPML\StringTranslation\Application\StringCore\Command\InsertStringTranslationsCheckedCommandInterface;
use WPML\StringTranslation\Application\StringCore\Command\InsertStringTranslationsCommandInterface;

class InsertStringTranslationsCommand extends BulkActionBaseCommand implements InsertStringTranslationsCommandInterface, InsertStringTranslationsCheckedCommandInterface {

	public function __construct(
		$wpdb
	) {
		$this->wpdb = $wpdb;
	}

	public function run( array $translations ) {
		foreach ( array_chunk( $translations, $this->chunk_size ) as $chunk ) {
			$query = "INSERT IGNORE INTO {$this->wpdb->prefix}icl_string_translations "
				. '(`string_id`, `language`, `status`, `value`, `mo_string`, `translator_id`, '
				. '`translation_service`, `batch_id`, `translation_date`) VALUES ';

			$query .= implode( ',', array_map( array( $this, 'buildStringTranslationRow' ), $chunk ) );

			$this->runBulkQuery( $query );
		}
	}

	public function runChecked( array $translations ) : int {
		$insertedRows = 0;
		foreach ( array_chunk( $translations, $this->chunk_size ) as $chunk ) {
			$query = "INSERT IGNORE INTO {$this->wpdb->prefix}icl_string_translations "
				. '(`string_id`, `language`, `status`, `value`, `mo_string`, `translator_id`, '
				. '`translation_service`, `batch_id`, `translation_date`) VALUES ';

			$query                 .= implode( ',', array_map( array( $this, 'buildStringTranslationRow' ), $chunk ) );
			$previousSuppressErrors = $this->wpdb->suppress_errors( true );
			try {
				$result = $this->wpdb->query( $query );
			} finally {
				$this->wpdb->suppress_errors( $previousSuppressErrors );
			}

			if ( false === $result ) {
				$message = isset( $this->wpdb->last_error ) && strlen( (string) $this->wpdb->last_error ) > 0
					? (string) $this->wpdb->last_error
					: 'Could not persist String Translation translations.';
				throw new \RuntimeException( $message );
			}

			$insertedRows += (int) $result;
		}

		return $insertedRows;
	}

	private function buildStringTranslationRow( StringTranslation $translation ) {
		return $this->wpdb->prepare(
			'(%s, %s, %s, %s, %s, %s, %s, %s, %s)',
			$translation->getString()->getId(),
			$translation->getLanguage(),
			ICL_TM_COMPLETE,
			$translation->getValue(),
			$translation->getValue(),
			null,
			'local',
			0,
			'2024-01-01 00:00:00'
		);
	}
}
