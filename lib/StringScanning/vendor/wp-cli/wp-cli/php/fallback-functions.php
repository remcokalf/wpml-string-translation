<?php


if ( PHP_MAJOR_VERSION >= 8 && ! function_exists( 'ini_set' ) ) {
	function ini_set( $option, $value ) {
		return false;
	}
}
