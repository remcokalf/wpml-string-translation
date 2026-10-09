<?php

namespace WP_CLI\Loggers;

use WP_CLI;

class Execution extends Regular {

	public $stdout = '';

	public $stderr = '';

	public function __construct( $in_color = false ) {
		parent::__construct( $in_color );
	}

	public function error_multi_line( $message_lines ) {
		$message = implode( "\n", $message_lines );

		$this->write( STDERR, WP_CLI::colorize( "%RError:%n\n$message\n" ) );
		$this->write( STDERR, WP_CLI::colorize( "%R---------%n\n\n" ) );
	}

	protected function write( $handle, $str ) {
		switch ( $handle ) {
			case STDOUT:
				$this->stdout .= $str;
				break;
			case STDERR:
				$this->stderr .= $str;
				break;
		}
	}

	public function ob_start() {
		ob_start( [ $this, 'ob_start_callback' ], 1 );
	}

	public function ob_start_callback( $str ) {
		$this->write( STDOUT, $str );
		return '';
	}

	public function ob_end() {
		ob_end_flush();
	}
}
