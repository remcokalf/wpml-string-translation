<?php

namespace WP_CLI\Loggers;

use cli\Colors;

class Regular extends Base {

	public function __construct( $in_color ) {
		$this->in_color = $in_color;
	}

	public function info( $message ) {
		$this->write( STDOUT, $message . "\n" );
	}

	public function success( $message ) {
		$this->_line( $message, 'Success', '%G' );
	}

	public function warning( $message ) {
		$this->_line( $message, 'Warning', '%C', STDERR );
	}

	public function error( $message ) {
		$this->_line( $message, 'Error', '%R', STDERR );
	}

	public function error_multi_line( $message_lines ) {
		$message_lines = array_map(
			function ( $line ) {
				return str_replace( "\t", '    ', $line );
			},
			$message_lines
		);

		$longest = max( array_map( 'strlen', $message_lines ) );

		$empty_line = Colors::colorize( '%w%1 ' . str_repeat( ' ', $longest ) . ' %n' );
		$this->write( STDERR, "\n\t$empty_line\n" );

		foreach ( $message_lines as $line ) {
			$padding = str_repeat( ' ', $longest - strlen( $line ) );
			$line    = Colors::colorize( "%w%1 $line $padding%n" );
			$this->write( STDERR, "\t$line\n" );
		}

		$this->write( STDERR, "\t$empty_line\n\n" );
	}
}
