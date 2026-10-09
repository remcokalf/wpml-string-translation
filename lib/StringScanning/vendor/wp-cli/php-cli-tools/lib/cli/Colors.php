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

class Colors {
	static protected $_colors = array(
		'color' => array(
			'black'   => 30,
			'red'	 => 31,
			'green'   => 32,
			'yellow'  => 33,
			'blue'	=> 34,
			'magenta' => 35,
			'cyan'	=> 36,
			'white'   => 37
		),
		'style' => array(
			'bright'	 => 1,
			'dim'		=> 2,
			'underline' => 4,
			'blink'	  => 5,
			'reverse'	=> 7,
			'hidden'	 => 8
		),
		'background' => array(
			'black'   => 40,
			'red'	 => 41,
			'green'   => 42,
			'yellow'  => 43,
			'blue'	=> 44,
			'magenta' => 45,
			'cyan'	=> 46,
			'white'   => 47
		)
	);
	static protected $_enabled = null;

	static protected $_string_cache = array();

	static public function enable($force = true) {
		self::$_enabled = $force === true ? true : null;
	}

	static public function disable($force = true) {
		self::$_enabled = $force === true ? false : null;
	}

	static public function shouldColorize($colored = null) {
		return self::$_enabled === true ||
			(self::$_enabled !== false &&
				($colored === true ||
					($colored !== false && Streams::isTty())));
	}

	static public function color($color) {
		if (!is_array($color)) {
			$color = compact('color');
		}

		$color += array('color' => null, 'style' => null, 'background' => null);

		if ($color['color'] == 'reset') {
			return "\033[0m";
		}

		$colors = array();
		foreach (array('color', 'style', 'background') as $type) {
			$code = $color[$type];
			if (isset(self::$_colors[$type][$code])) {
				$colors[] = self::$_colors[$type][$code];
			}
		}

		if (empty($colors)) {
			$colors[] = 0;
		}

		return "\033[" . join(';', $colors) . "m";
	}

	static public function colorize($string, $colored = null) {
		$passed = $string;

		if (!self::shouldColorize($colored)) {
			$return = self::decolorize( $passed, 2   );
			self::cacheString($passed, $return);
			return $return;
		}

		$md5 = md5($passed);
		if (isset(self::$_string_cache[$md5]['colorized'])) {
			return self::$_string_cache[$md5]['colorized'];
		}

		$string = str_replace('%%', '%¾', $string);

		foreach (self::getColors() as $key => $value) {
			$string = str_replace($key, self::color($value), $string);
		}

		$string = str_replace('%¾', '%', $string);
		self::cacheString($passed, $string);

		return $string;
	}

	static public function decolorize( $string, $keep = 0 ) {
		$string = (string) $string;
		
		if ( ! ( $keep & 1 ) ) {
			$string = str_replace('%%', '%¾', $string);
			$string = str_replace(array_keys(self::getColors()), '', $string);
			$string = str_replace('%¾', '%', $string);
		}

		if ( ! ( $keep & 2 ) ) {
			foreach (self::getColors() as $key => $value) {
				$string = str_replace(self::color($value), '', $string);
			}
		}

		return $string;
	}

	static public function cacheString( $passed, $colorized, $deprecated = null ) {
		self::$_string_cache[md5($passed)] = array(
			'passed'      => $passed,
			'colorized'   => $colorized,
			'decolorized' => self::decolorize($passed),
		);
	}

	static public function length($string) {
		return safe_strlen( self::decolorize( $string ) );
	}

	static public function width( $string, $pre_colorized = false, $encoding = false ) {
		return strwidth( $pre_colorized || self::shouldColorize() ? self::decolorize( $string, $pre_colorized ? 1   : 0 ) : $string, $encoding );
	}

	static public function pad( $string, $length, $pre_colorized = false, $encoding = false, $pad_type = STR_PAD_RIGHT ) {
		$string = (string) $string;
		
		$real_length = self::width( $string, $pre_colorized, $encoding );
		$diff = strlen( $string ) - $real_length;
		$length += $diff;

		return str_pad( $string, $length, ' ', $pad_type );
	}

	static public function getColors() {
		return array(
			'%y' => array('color' => 'yellow'),
			'%g' => array('color' => 'green'),
			'%b' => array('color' => 'blue'),
			'%r' => array('color' => 'red'),
			'%p' => array('color' => 'magenta'),
			'%m' => array('color' => 'magenta'),
			'%c' => array('color' => 'cyan'),
			'%w' => array('color' => 'white'),
			'%k' => array('color' => 'black'),
			'%n' => array('color' => 'reset'),
			'%Y' => array('color' => 'yellow', 'style' => 'bright'),
			'%G' => array('color' => 'green', 'style' => 'bright'),
			'%B' => array('color' => 'blue', 'style' => 'bright'),
			'%R' => array('color' => 'red', 'style' => 'bright'),
			'%P' => array('color' => 'magenta', 'style' => 'bright'),
			'%M' => array('color' => 'magenta', 'style' => 'bright'),
			'%C' => array('color' => 'cyan', 'style' => 'bright'),
			'%W' => array('color' => 'white', 'style' => 'bright'),
			'%K' => array('color' => 'black', 'style' => 'bright'),
			'%N' => array('color' => 'reset', 'style' => 'bright'),
			'%3' => array('background' => 'yellow'),
			'%2' => array('background' => 'green'),
			'%4' => array('background' => 'blue'),
			'%1' => array('background' => 'red'),
			'%5' => array('background' => 'magenta'),
			'%6' => array('background' => 'cyan'),
			'%7' => array('background' => 'white'),
			'%0' => array('background' => 'black'),
			'%F' => array('style' => 'blink'),
			'%U' => array('style' => 'underline'),
			'%8' => array('style' => 'reverse'),
			'%9' => array('style' => 'bright'),
			'%_' => array('style' => 'bright')
		);
	}

	static public function getStringCache() {
		return self::$_string_cache;
	}

	static public function clearStringCache() {
		self::$_string_cache = array();
	}
}
