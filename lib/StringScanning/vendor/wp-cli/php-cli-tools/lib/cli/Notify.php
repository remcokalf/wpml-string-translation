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

use cli\Streams;

abstract class Notify {
	protected $_current = 0;
	protected $_first = true;
	protected $_interval;
	protected $_message;
	protected $_start;
	protected $_timer;
	protected $_tick;
	protected $_iteration = 0;
	protected $_speed = 0;

	public function __construct($msg, $interval = 100) {
		$this->_message = $msg;
		$this->_interval = (int)$interval;
	}

	abstract public function display($finish = false);

	public function reset() {
		$this->_current = 0;
		$this->_first = true;
		$this->_start = null;
		$this->_timer = null;
	}

	public function current() {
		return number_format($this->_current);
	}

	public function elapsed() {
		if (!$this->_start) {
			return 0;
		}

		$elapsed = time() - $this->_start;
		return $elapsed;
	}

	public function speed() {
		if (!$this->_start) {
			return 0;
		} else if (!$this->_tick) {
			$this->_tick = $this->_start;
		}

		$now = microtime(true);
		$span = $now - $this->_tick;
		if ($span > 1) {
			$this->_iteration++;
			$this->_tick = $now;
			$this->_speed = ($this->_current / $this->_iteration) / $span;
		}

		return $this->_speed;
	}

	public function formatTime($time) {
		return floor($time / 60) . ':' . str_pad($time % 60, 2, 0, STR_PAD_LEFT);
	}

	public function finish() {
		Streams::out("\r");
		$this->display(true);
		Streams::line();
	}

	public function increment($increment = 1) {
		$this->_current += $increment;
	}

	public function shouldUpdate() {
		$now = microtime(true) * 1000;

		if (empty($this->_timer)) {
			$this->_start = (int)(($this->_timer = $now) / 1000);
			return true;
		}

		if (($now - $this->_timer) > $this->_interval) {
			$this->_timer = $now;
			return true;
		}
		return false;
	}

	public function tick($increment = 1) {
		$this->increment($increment);

		if ($this->shouldUpdate()) {
			Streams::out("\r");
			$this->display();
		}
	}
}
