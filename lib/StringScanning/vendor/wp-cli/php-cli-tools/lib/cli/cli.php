<?php

/**
 * PHP Command Line Tools
 *
 * This source file is subject to the MIT license that is bundled
 * with this package in the file LICENSE.
 *
 * @author    James Logsdon <dwarf@girsbrain.org>
 * @copyright 2010 James Logsdom (http://girsbrain.org)
 * @license   http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace cli;

function render( $msg ) {
	return Streams::_call( 'render', func_get_args() );
}

function out( $msg ) {
	Streams::_call( 'out', func_get_args() );
}

function out_padded( $msg ) {
	Streams::_call( 'out_padded', func_get_args() );
}

function line( $msg = '' ) {
	Streams::_call( 'line', func_get_args() );
}

function err( $msg = '' ) {
	Streams::_call( 'err', func_get_args() );
}

function input( $format = null ) {
	return Streams::input( $format );
}

function prompt( $question, $default = false, $marker = ': ', $hide = false ) {
	return Streams::prompt( $question, $default, $marker, $hide );
}

function choose( $question, $choice = 'yn', $default = 'n' ) {
	return Streams::choose( $question, $choice, $default );
}

function confirm( $question, $default = false ) {
	if ( is_bool( $default ) ) {
		$default = $default? 'y' : 'n';
	}
	$result  = choose( $question, 'yn', $default );
	return $result == 'y';
}

function menu( $items, $default = null, $title = 'Choose an item' ) {
	return Streams::menu( $items, $default, $title );
}

function safe_strlen( $str, $encoding = false ) {
	$test_safe_strlen = getenv( 'PHP_CLI_TOOLS_TEST_SAFE_STRLEN' );

	if ( ( ! $encoding || 'UTF-8' === $encoding ) && can_use_icu() && null !== ( $length = grapheme_strlen( $str ) ) ) {
		if ( ! $test_safe_strlen || ( $test_safe_strlen & 1 ) ) {
			return $length;
		}
	}
	if ( ( ! $encoding || 'UTF-8' === $encoding ) && can_use_pcre_x() && false !== ( $length = preg_match_all( '/\X/u', $str, $dummy   ) ) ) {
		if ( ! $test_safe_strlen || ( $test_safe_strlen & 2 ) ) {
			return $length;
		}
	}
	if ( function_exists( 'mb_strlen' ) && ( $encoding || function_exists( 'mb_detect_encoding' ) ) ) {
		if ( ! $encoding ) {
			$encoding = mb_detect_encoding( $str, null, true   );
		}
		$length = $encoding ? mb_strlen( $str, $encoding ) : mb_strlen( $str );
		if ( 'UTF-8' === $encoding ) {
			$length -= preg_match_all( get_unicode_regexs( 'm' ), $str, $dummy   );
		}
		if ( ! $test_safe_strlen || ( $test_safe_strlen & 4 ) ) {
			return $length;
		}
	}
	return strlen( $str );
}

function safe_substr( $str, $start, $length = false, $is_width = false, $encoding = false ) {
	if ( $length < 0 || ( $is_width && ( null === $length || false === $length ) ) ) {
		return false;
	}
	$safe_strlen = safe_strlen( $str, $encoding );

	if ( null === $length || false === $length ) {
		$length = $safe_strlen;
	}
	if ( $start > $safe_strlen ) {
		return '';
	}
	if ( $start < 0 && -$start > $safe_strlen ) {
		$start = 0;
	}

	$test_safe_substr = getenv( 'PHP_CLI_TOOLS_TEST_SAFE_SUBSTR' );

	if ( ( ! $encoding || 'UTF-8' === $encoding ) && can_use_icu() && false !== ( $try = grapheme_substr( $str, $start, $length ) ) ) {
		if ( ! $test_safe_substr || ( $test_safe_substr & 1 ) ) {
			return $is_width ? _safe_substr_eaw( $try, $length ) : $try;
		}
	}
	if ( ( ! $encoding || 'UTF-8' === $encoding ) && can_use_pcre_x() ) {
		if ( false !== ( $try = preg_split( '/(\X)/u', $str, $safe_strlen + 1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY ) ) && ! preg_last_error() ) {
			$try = implode( '', array_slice( $try, $start, $length ) );
			if ( ! $test_safe_substr || ( $test_safe_substr & 2 ) ) {
				return $is_width ? _safe_substr_eaw( $try, $length ) : $try;
			}
		}
	}
	if ( function_exists( 'mb_substr' ) && ( $encoding || function_exists( 'mb_detect_encoding' ) ) ) {
		if ( ! $encoding ) {
			$encoding = mb_detect_encoding( $str, null, true   );
		}
		$try = $encoding ? mb_substr( $str, $start, $length, $encoding ) : mb_substr( $str, $start, $length );
		if ( 'UTF-8' === $encoding && $is_width ) {
			$try = _safe_substr_eaw( $try, $length );
		}
		if ( ! $test_safe_substr || ( $test_safe_substr & 4 ) ) {
			return $try;
		}
	}
	return substr( $str, $start, $length );
}

function _safe_substr_eaw( $str, $length ) {
	$eaw_regex = get_unicode_regexs( 'eaw' );

	if ( preg_match( $eaw_regex, $str ) ) {

		if ( function_exists( 'mb_substr' ) && preg_match_all( $eaw_regex, $str, $dummy   ) === $length ) {
			$str = mb_substr( $str, 0, max( (int) ( $length / 2 ), 1 ), 'UTF-8' );
		} else {
			$chars = preg_split( '/([\x00-\x7f\xc2-\xf4][^\x00-\x7f\xc2-\xf4]*)/', $str, $length + 1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY );
			$cnt = min( count( $chars ), $length );
			$width = $length;

			for ( $length = 0; $length < $cnt && $width > 0; $length++ ) {
				$width -= preg_match( $eaw_regex, $chars[ $length ] ) ? 2 : 1;
			}
			if ( $width < 0 && $length > 1 ) {
				$length--;
			}
			return join( '', array_slice( $chars, 0, $length ) );
		}
	}
	return $str;
}

function safe_str_pad( $string, $length, $encoding = false ) {
	$real_length = strwidth( $string, $encoding );
	$diff = strlen( $string ) - $real_length;
	$length += $diff;

	return str_pad( $string, $length );
}

function strwidth( $string, $encoding = false ) {
	$string = (string) $string;
	
	list( $eaw_regex, $m_regex ) = get_unicode_regexs();

	$test_strwidth = getenv( 'PHP_CLI_TOOLS_TEST_STRWIDTH' );

	if ( ( ! $encoding || 'UTF-8' === $encoding ) && can_use_icu() && null !== ( $width = grapheme_strlen( $string ) ) ) {
		if ( ! $test_strwidth || ( $test_strwidth & 1 ) ) {
			return $width + preg_match_all( $eaw_regex, $string, $dummy   );
		}
	}
	if ( ( ! $encoding || 'UTF-8' === $encoding ) && can_use_pcre_x() && false !== ( $width = preg_match_all( '/\X/u', $string, $dummy   ) ) ) {
		if ( ! $test_strwidth || ( $test_strwidth & 2 ) ) {
			return $width + preg_match_all( $eaw_regex, $string, $dummy   );
		}
	}
	if ( function_exists( 'mb_strwidth' ) && ( $encoding || function_exists( 'mb_detect_encoding' ) ) ) {
		if ( ! $encoding ) {
			$encoding = mb_detect_encoding( $string, null, true   );
		}
		$width = $encoding ? mb_strwidth( $string, $encoding ) : mb_strwidth( $string );
		if ( 'UTF-8' === $encoding ) {
			$width -= preg_match_all( $m_regex, $string, $dummy   );
		}
		if ( ! $test_strwidth || ( $test_strwidth & 4 ) ) {
			return $width;
		}
	}
	return safe_strlen( $string, $encoding );
}

function can_use_icu() {
	static $can_use_icu = null;

	if ( null === $can_use_icu ) {
		$can_use_icu = defined( 'INTL_ICU_VERSION' ) && version_compare( INTL_ICU_VERSION, '54.1', '>=' ) && function_exists( 'grapheme_strlen' ) && function_exists( 'grapheme_substr' );
	}

	return $can_use_icu;
}

function can_use_pcre_x() {
	static $can_use_pcre_x = null;

	if ( null === $can_use_pcre_x ) {
		$pcre_version = substr( PCRE_VERSION, 0, strspn( PCRE_VERSION, '0123456789.' ) );
		$can_use_pcre_x = version_compare( $pcre_version, '8.32', '>=' ) && false !== @preg_match( '/\X/u', '' );
	}

	return $can_use_pcre_x;
}

function get_unicode_regexs( $idx = null ) {
	static $eaw_regex;
	static $m_regex;
	if ( null === $eaw_regex ) {
		require __DIR__ . '/unicode/regex.php';
	}

	if ( null !== $idx ) {
		if ( 'eaw' === $idx ) {
			return $eaw_regex;
		}
		if ( 'm' === $idx ) {
			return $m_regex;
		}
	}

	return array( $eaw_regex, $m_regex, );
}
