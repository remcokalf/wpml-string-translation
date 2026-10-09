<?php

namespace WP_CLI\Loggers;

use cli\Colors;
use WP_CLI;
use WP_CLI\Runner;

abstract class Base {

	protected $in_color = false;

	abstract public function info( $message );

	abstract public function success( $message );

	abstract public function warning( $message );

	protected function get_runner() {
		return WP_CLI::get_runner();
	}

	public function debug( $message, $group = false ) {
		static $start_time = null;
		if ( null === $start_time ) {
			$start_time = microtime( true );
		}
		$debug = $this->get_runner()->config['debug'];
		if ( ! $debug ) {
			return;
		}
		if ( true !== $debug && $group !== $debug ) {
			return;
		}
		$time   = round( microtime( true ) - ( defined( 'WP_CLI_START_MICROTIME' ) ? WP_CLI_START_MICROTIME : $start_time ), 3 );
		$prefix = 'Debug';
		if ( $group && true === $debug ) {
			$prefix = 'Debug (' . $group . ')';
		}
		$this->_line( "$message ({$time}s)", $prefix, '%B', STDERR );
	}

	protected function write( $handle, $str ) {
		fwrite( $handle, $str );
	}

	protected function _line( $message, $label, $color, $handle = STDOUT ) {
		if ( class_exists( 'cli\Colors' ) ) {
			$label = Colors::colorize( "$color$label:%n", $this->in_color );
		} else {
			$label = "$label:";
		}
		$this->write( $handle, "$label $message\n" );
	}
}
