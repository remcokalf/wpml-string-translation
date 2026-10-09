<?php

namespace WP_CLI;

use Composer\IO\NullIO;
use WP_CLI;

class ComposerIO extends NullIO {

	public function isVerbose() {
		return true;
	}

	public function write( $messages, $newline = true, $verbosity = self::NORMAL ) {
		self::output_clean_message( $messages );
	}

	public function writeError( $messages, $newline = true, $verbosity = self::NORMAL ) {
		self::output_clean_message( $messages );
	}

	private static function output_clean_message( $messages ) {
		$messages = (array) preg_replace( '#<(https?)([^>]+)>#', '$1$2', $messages );
		foreach ( $messages as $message ) {
			WP_CLI::log( strip_tags( trim( $message ) ) );
		}
	}
}
