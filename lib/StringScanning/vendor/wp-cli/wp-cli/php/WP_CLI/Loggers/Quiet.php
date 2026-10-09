<?php

namespace WP_CLI\Loggers;

use WP_CLI;

class Quiet extends Base {

	public function __construct( $in_color = false ) {
		$this->in_color = $in_color;
	}

	public function info( $message ) {
	}

	public function success( $message ) {
	}

	public function warning( $message ) {
	}

	public function error( $message ) {
		$this->_line( $message, 'Error', '%R', STDERR );
	}

	public function error_multi_line( $message_lines ) {
		$message = implode( "\n", $message_lines );

		$this->_line( $message, 'Error', '%R', STDERR );
		$this->_line( '', '---------', '%R', STDERR );
	}
}
