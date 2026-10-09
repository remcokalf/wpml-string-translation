<?php

namespace cli;

class Streams {

	protected static $out = STDOUT;
	protected static $in = STDIN;
	protected static $err = STDERR;

	static function _call( $func, $args ) {
		$method = __CLASS__ . '::' . $func;
		return call_user_func_array( $method, $args );
	}

	static public function isTty() {
		if ( function_exists('stream_isatty') ) {
			return stream_isatty(static::$out);
		} else {
			return (function_exists('posix_isatty') && posix_isatty(static::$out));
		}
	}

	public static function render( $msg ) {
		$args = func_get_args();

		if( count( $args ) == 1 || ( is_string( $args[1] ) && '' === $args[1] ) ) {
			return Colors::shouldColorize() ? Colors::colorize( $msg ) : $msg;
		}

		if( !is_array( $args[1] ) ) {
			if ( Colors::shouldColorize() ) {
				$args[0] = Colors::colorize( $args[0] );
			}

			$args[0] = preg_replace('/(%([^\w]|$))/', "%$1", $args[0]);

			return call_user_func_array( 'sprintf', $args );
		}

		foreach( $args[1] as $key => $value ) {
			$msg = str_replace( '{:' . $key . '}', $value, $msg );
		}
		return Colors::shouldColorize() ? Colors::colorize( $msg ) : $msg;
	}

	public static function out( $msg ) {
		fwrite( static::$out, self::_call( 'render', func_get_args() ) );
	}

	public static function out_padded( $msg ) {
		$msg = self::_call( 'render', func_get_args() );
		self::out( str_pad( $msg, \cli\Shell::columns() ) );
	}

	public static function line( $msg = '' ) {
		$args = array_merge( func_get_args(), array( '' ) );
		$args[0] .= "\n";

		self::_call( 'out', $args );
	}

	public static function err( $msg = '' ) {
		$args = array_merge( func_get_args(), array( '' ) );
		$args[0] .= "\n";
		fwrite( static::$err, self::_call( 'render', $args ) );
	}

	public static function input( $format = null, $hide = false ) {
		if ( $hide )
			Shell::hide();

		if( $format ) {
			fscanf( static::$in, $format . "\n", $line );
		} else {
			$line = fgets( static::$in );
		}

		if ( $hide ) {
			Shell::hide( false );
			echo "\n";
		}

		if( $line === false ) {
			throw new \Exception( 'Caught ^D during input' );
		}

		return trim( $line );
	}

	public static function prompt( $question, $default = false, $marker = ': ', $hide = false ) {
		if( $default && strpos( $question, '[' ) === false ) {
			$question .= ' [' . $default . ']';
		}

		while( true ) {
			self::out( $question . $marker );
			$line = self::input( null, $hide );

			if ( trim( $line ) !== '' )
				return $line;
			if( $default !== false )
				return $default;
		}
	}

	public static function choose( $question, $choice = 'yn', $default = 'n' ) {
		if( !is_string( $choice ) ) {
			$choice = join( '', $choice );
		}

		$choice = str_ireplace( $default, strtoupper( $default ), strtolower( $choice ) );
		$choices = trim( join( '/', preg_split( '//', $choice ) ), '/' );

		while( true ) {
			$line = self::prompt( sprintf( '%s? [%s]', $question, $choices ), $default, '' );

			if( stripos( $choice, $line ) !== false ) {
				return strtolower( $line );
			}
			if( !empty( $default ) ) {
				return strtolower( $default );
			}
		}
	}

	public static function menu( $items, $default = null, $title = 'Choose an item' ) {
		$map = array_values( $items );

		if( $default && strpos( $title, '[' ) === false && isset( $items[$default] ) ) {
			$title .= ' [' . $items[$default] . ']';
		}

		foreach( $map as $idx => $item ) {
			self::line( '  %d. %s', $idx + 1, (string)$item );
		}
		self::line();

		while( true ) {
			fwrite( static::$out, sprintf( '%s: ', $title ) );
			$line = self::input();

			if( is_numeric( $line ) ) {
				$line--;
				if( isset( $map[$line] ) ) {
					return array_search( $map[$line], $items );
				}

				if( $line < 0 || $line >= count( $map ) ) {
					self::err( 'Invalid menu selection: out of range' );
				}
			} else if( isset( $default ) ) {
				return $default;
			}
		}
	}

	public static function setStream( $whichStream, $stream ) {
		if( !is_resource( $stream ) || get_resource_type( $stream ) !== 'stream' ) {
			throw new \Exception( 'Invalid resource type!' );
		}
		if( property_exists( __CLASS__, $whichStream ) ) {
			static::${$whichStream} = $stream;
		}
		register_shutdown_function( function() use ($stream) {
			fclose( $stream );
		} );
	}

}
