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

class Lexer extends Memoize implements \Iterator {
	private $_item;
	private $_items = array();
	private $_index = 0;
	private $_length = 0;
	private $_first = true;

	public function __construct(array $items) {
		$this->_items = $items;
		$this->_length = count($items);
	}

	#[\ReturnTypeWillChange]
	public function current() {
		return $this->_item;
	}

	public function peek() {
		return new Argument($this->_items[0]);
	}

	#[\ReturnTypeWillChange]
	public function next() {
		if ($this->valid()) {
			$this->_shift();
		}
	}

	#[\ReturnTypeWillChange]
	public function key() {
		return $this->_index;
	}

	#[\ReturnTypeWillChange]
	public function rewind() {
		$this->_shift();
		if ($this->_first) {
			$this->_index = 0;
			$this->_first = false;
		}
	}

	#[\ReturnTypeWillChange]
	public function valid() {
		return ($this->_index < $this->_length);
	}

	public function unshift($item) {
		array_unshift($this->_items, $item);
		$this->_length += 1;
	}

	public function end() {
		return ($this->_index + 1) == $this->_length;
	}

	private function _shift() {
		$this->_item = new Argument(array_shift($this->_items));
		$this->_index += 1;
		$this->_explode();
		$this->_unmemo('peek');
	}

	private function _explode() {
		if (!$this->_item->canExplode) {
			return false;
		}

		foreach ($this->_item->exploded as $piece) {
			$this->unshift('-' . $piece);
		}
	}
}
