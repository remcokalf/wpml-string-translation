<?php

namespace WP_CLI\I18n;

class FileDataExtractor {
	public static function get_file_data( $file, $headers ) {
		$fp = fopen( $file, 'rb' );

		$file_data = fread( $fp, 8192 );

		fclose( $fp );

		$file_data = str_replace( "\r", "\n", $file_data );

		return static::get_file_data_from_string( $file_data, $headers );
	}

	public static function get_file_data_from_string( $text, $headers ) {
		foreach ( $headers as $field => $regex ) {
			if ( preg_match( '/^[ \t\/*#@]*' . preg_quote( $regex, '/' ) . ':(.*)$/mi', $text, $match ) && $match[1] ) {
				$headers[ $field ] = static::_cleanup_header_comment( $match[1] );
			} else {
				$headers[ $field ] = '';
			}
		}

		return $headers;
	}

	protected static function _cleanup_header_comment( $str ) {
		return trim( preg_replace( '/\s*(?:\*\/|\?>).*/', '', $str ) );
	}
}
