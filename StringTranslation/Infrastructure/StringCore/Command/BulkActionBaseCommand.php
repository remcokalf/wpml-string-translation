<?php

namespace WPML\StringTranslation\Infrastructure\StringCore\Command;

abstract class BulkActionBaseCommand {

	protected $wpdb;

	protected $chunk_size = 1000;

	protected function runBulkQuery( string $query ) {
		$this->wpdb->suppress_errors = true;
		$this->wpdb->query( $query );
		$this->wpdb->suppress_errors = false;
	}

	protected function runCheckedBulkQuery( string $query, string $fallbackMessage ) : int {
		$previousSuppressErrors = $this->wpdb->suppress_errors( true );
		try {
			$result = $this->wpdb->query( $query );
		} finally {
			$this->wpdb->suppress_errors( $previousSuppressErrors );
		}

		if ( false === $result ) {
			$message = isset( $this->wpdb->last_error ) && strlen( (string) $this->wpdb->last_error ) > 0
				? (string) $this->wpdb->last_error
				: $fallbackMessage;
			throw new \RuntimeException( $message );
		}

		return (int) $result;
	}
}
