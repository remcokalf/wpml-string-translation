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

namespace cli\arguments;

use cli\Memoize;

class Argument extends Memoize {
	public $key;

	private $_argument;
	private $_raw;

	public function __construct($argument) {
		$this->_raw = $argument;
		$this->key =& $this->_argument;

		if ($this->isLong) {
			$this->_argument = substr($this->_raw, 2);
		} else if ($this->isShort) {
			$this->_argument = substr($this->_raw, 1);
		} else {
			$this->_argument = $this->_raw;
		}
	}

	public function __toString() {
		return (string)$this->_raw;
	}

	public function value() {
		return $this->_argument;
	}

	public function raw() {
		return $this->_raw;
	}

	public function isLong() {
		return (0 == strncmp((string)$this->_raw, '--', 2));
	}

	public function isShort() {
		return !$this->isLong && (0 == strncmp((string)$this->_raw, '-', 1));
	}

	public function isArgument() {
		return $this->isShort() || $this->isLong();
	}

	public function isValue() {
		return !$this->isArgument;
	}

	public function canExplode() {
		return $this->isShort && strlen($this->_argument) > 1;
	}

	public function exploded() {
		$exploded = array();

		for ($i = strlen($this->_argument); $i > 0; $i--) {
			array_push($exploded, $this->_argument[$i - 1]);
		}

		$this->_argument = array_pop($exploded);
		$this->_raw      = '-' . $this->_argument;
		return $exploded;
	}
}
