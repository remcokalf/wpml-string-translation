<?php

namespace WPML\StringTranslation\Infrastructure\StringCore\Command;

use WPML\StringTranslation\Application\StringCore\Domain\StringItem;
use WPML\StringTranslation\Application\StringCore\Command\UpdateStringsCommandInterface;

class UpdateStringsCommand extends BulkActionBaseCommand implements UpdateStringsCommandInterface {

	public function __construct(
		$wpdb
	) {
		$this->wpdb = $wpdb;
	}

	public function run( array $strings, array $fields, array $values ) {
		$fieldCount = count( $fields );
		foreach ( array_chunk( $strings, $this->chunk_size ) as $chunk ) {
			$ids = [];
			foreach ( $chunk as $string ) {
				$ids[] = $string->getId();
			}

			$query  = '';
			$query .= "UPDATE {$this->wpdb->prefix}icl_strings SET ";

			$fieldsSql = [];
			for ( $i = 0; $i < $fieldCount; $i++ ) {
				$preparedValue = $this->wpdb->prepare( '%s', $values[ $i ] );
				if ( ! is_string( $preparedValue ) ) {
					throw new \RuntimeException( 'Could not prepare a String Translation strings update.' );
				}
				$fieldsSql[] = sanitize_key( $fields[ $i ] ) . ' = ' . $preparedValue;
			}

			$query .= implode( ', ', $fieldsSql );
			$query .= ' WHERE id IN (' . wpml_prepare_in( $ids, '%d' ) . ')';

			$this->runCheckedBulkQuery(
				$query,
				'Could not update String Translation strings.'
			);
		}
	}
}
