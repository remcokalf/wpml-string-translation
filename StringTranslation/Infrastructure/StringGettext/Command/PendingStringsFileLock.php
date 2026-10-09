<?php

namespace WPML\StringTranslation\Infrastructure\StringGettext\Command;

class PendingStringsFileLock {

	const LOCK_WAIT_MICROSECONDS  = 50000;
	const LOCK_RETRY_MICROSECONDS = 5000;

	public function acquire( string $lockFilepath ) {
		$lock = @fopen( $lockFilepath, 'c' );
		if ( ! $lock ) {
			return null;
		}

		$start = microtime( true );
		while ( ! flock( $lock, LOCK_EX | LOCK_NB ) ) {
			if ( ( microtime( true ) - $start ) * 1000000 >= self::LOCK_WAIT_MICROSECONDS ) {
				fclose( $lock );
				return null;
			}

			usleep( self::LOCK_RETRY_MICROSECONDS );
		}

		return $lock;
	}

	public function release( $lock ) {
		if ( ! is_resource( $lock ) ) {
			return;
		}

		flock( $lock, LOCK_UN );
		fclose( $lock );
	}
}
