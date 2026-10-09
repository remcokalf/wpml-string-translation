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

abstract class Progress extends \cli\Notify {
	protected $_total = 0;

	public function __construct($msg, $total, $interval = 100) {
		parent::__construct($msg, $interval);
		$this->setTotal($total);
	}

	public function setTotal($total) {
		$this->_total = (int)$total;

		if ($this->_total < 0) {
			throw new \InvalidArgumentException('Maximum value out of range, must be positive.');
		}
	}

	public function reset($total = null) {
		parent::reset();

		if ($total) {
			$this->setTotal($total);
		}
	}

	public function current() {
		$size = strlen($this->total());
		return str_pad(parent::current(), $size);
	}

	public function total() {
		return number_format($this->_total);
	}

	public function estimated() {
		$speed = $this->speed();
		if (!$speed || !$this->elapsed()) {
			return 0;
		}

		$estimated = round($this->_total / $speed);
		return $estimated;
	}

	public function finish() {
		$this->_current = $this->_total;
		parent::finish();
	}

	public function increment($increment = 1) {
		$this->_current = min($this->_total, $this->_current + $increment);
	}

	public function percent() {
		if ($this->_total == 0) {
			return 1;
		}

		return ($this->_current / $this->_total);
	}
}
