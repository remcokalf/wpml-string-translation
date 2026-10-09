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

use cli\arguments\Argument;
use cli\arguments\HelpScreen;
use cli\arguments\InvalidArguments;
use cli\arguments\Lexer;

class Arguments implements \ArrayAccess {
	protected $_flags = array();
	protected $_options = array();
	protected $_strict = false;
	protected $_input = array();
	protected $_invalid = array();
	protected $_parsed;
	protected $_lexer;

	public function __construct($options = array()) {
		$options += array(
			'strict' => false,
			'input'  => array_slice($_SERVER['argv'], 1)
		);

		$this->_input = $options['input'];
		$this->setStrict($options['strict']);

		if (isset($options['flags'])) {
			$this->addFlags($options['flags']);
		}
		if (isset($options['options'])) {
			$this->addOptions($options['options']);
		}
	}

	public function getArguments() {
		if (!isset($this->_parsed)) {
			$this->parse();
		}
		return $this->_parsed;
	}

	public function getHelpScreen() {
		return new HelpScreen($this);
	}

	public function asJSON() {
		return json_encode($this->_parsed);
	}

	#[\ReturnTypeWillChange]
	public function offsetExists($offset) {
		if ($offset instanceOf Argument) {
			$offset = $offset->key;
		}

		return array_key_exists($offset, $this->_parsed);
	}

	#[\ReturnTypeWillChange]
	public function offsetGet($offset) {
		if ($offset instanceOf Argument) {
			$offset = $offset->key;
		}

		if (isset($this->_parsed[$offset])) {
			return $this->_parsed[$offset];
		}
	}

	#[\ReturnTypeWillChange]
	public function offsetSet($offset, $value) {
		if ($offset instanceOf Argument) {
			$offset = $offset->key;
		}

		$this->_parsed[$offset] = $value;
	}

	#[\ReturnTypeWillChange]
	public function offsetUnset($offset) {
		if ($offset instanceOf Argument) {
			$offset = $offset->key;
		}

		unset($this->_parsed[$offset]);
	}

	public function addFlag($flag, $settings = array()) {
		if (is_string($settings)) {
			$settings = array('description' => $settings);
		}
		if (is_array($flag)) {
			$settings['aliases'] = $flag;
			$flag = array_shift($settings['aliases']);
		}
		if (isset($this->_flags[$flag])) {
			$this->_warn('flag already exists: ' . $flag);
			return $this;
		}

		$settings += array(
			'default'     => false,
			'stackable'   => false,
			'description' => null,
			'aliases'     => array()
		);

		$this->_flags[$flag] = $settings;
		return $this;
	}

	public function addFlags($flags) {
		foreach ($flags as $flag => $settings) {
			if (is_numeric($flag)) {
				$this->_warn('No flag character given');
				continue;
			}

			$this->addFlag($flag, $settings);
		}

		return $this;
	}

	public function addOption($option, $settings = array()) {
		if (is_string($settings)) {
			$settings = array('description' => $settings);
		}
		if (is_array($option)) {
			$settings['aliases'] = $option;
			$option = array_shift($settings['aliases']);
		}
		if (isset($this->_options[$option])) {
			$this->_warn('option already exists: ' . $option);
			return $this;
		}

		$settings += array(
			'default'     => null,
			'description' => null,
			'aliases'     => array()
		);

		$this->_options[$option] = $settings;
		return $this;
	}

	public function addOptions($options) {
		foreach ($options as $option => $settings) {
			if (is_numeric($option)) {
				$this->_warn('No option string given');
				continue;
			}

			$this->addOption($option, $settings);
		}

		return $this;
	}

	public function setStrict($strict) {
		$this->_strict = (bool)$strict;
		return $this;
	}

	public function getInvalidArguments() {
		return $this->_invalid;
	}

	public function getFlag($flag) {
		if ($flag instanceOf Argument) {
			$obj  = $flag;
			$flag = $flag->value;
		}

		if (isset($this->_flags[$flag])) {
			return $this->_flags[$flag];
		}

		foreach ($this->_flags as $master => $settings) {
			if (in_array($flag, (array)$settings['aliases'])) {
				if (isset($obj)) {
					$obj->key = $master;
				}

				$cache[$flag] =& $settings;
				return $settings;
			}
		}
	}

	public function getFlags() {
		return $this->_flags;
	}

	public function hasFlags() {
		return !empty($this->_flags);
	}

	public function isFlag($argument) {
		return (null !== $this->getFlag($argument));
	}

	public function isStackable($flag) {
		$settings = $this->getFlag($flag);

		return isset($settings) && (true === $settings['stackable']);
	}

	public function getOption($option) {
		if ($option instanceOf Argument) {
			$obj = $option;
			$option = $option->value;
		}

		if (isset($this->_options[$option])) {
			return $this->_options[$option];
		}

		foreach ($this->_options as $master => $settings) {
			if (in_array($option, (array)$settings['aliases'])) {
				if (isset($obj)) {
					$obj->key = $master;
				}

				return $settings;
			}
		}
	}

	public function getOptions() {
		return $this->_options;
	}

	public function hasOptions() {
		return !empty($this->_options);
	}

	public function isOption($argument) {
		return (null != $this->getOption($argument));
	}

	public function parse() {
		$this->_invalid = array();
		$this->_parsed = array();
		$this->_lexer = new Lexer($this->_input);

		$this->_applyDefaults();

		foreach ($this->_lexer as $argument) {
			if ($this->_parseFlag($argument)) {
				continue;
			}
			if ($this->_parseOption($argument)) {
				continue;
			}

			array_push($this->_invalid, $argument->raw);
		}

		if ($this->_strict && !empty($this->_invalid)) {
			throw new InvalidArguments($this->_invalid);
		}
	}

	private function _applyDefaults() {
		foreach($this->_flags as $flag => $settings) {
			$this[$flag] = $settings['default'];
		}

		foreach($this->_options as $option => $settings) {
			if (!empty($settings['default']) || $settings['default'] === 0) {
				$this[$option] = $settings['default'];
			}
		}
	}

	private function _warn($message) {
		trigger_error('[' . __CLASS__ .'] ' . $message, E_USER_WARNING);
	}

	private function _parseFlag($argument) {
		if (!$this->isFlag($argument)) {
			return false;
		}

		if ($this->isStackable($argument)) {
			if (!isset($this[$argument])) {
				$this[$argument->key] = 0;
			}

			$this[$argument->key] += 1;
		} else {
			$this[$argument->key] = true;
		}

		return true;
	}

	private function _parseOption($option) {
		if (!$this->isOption($option)) {
			return false;
		}

		if ($this->_lexer->end() || !$this->_lexer->peek->isValue) {
			$optionSettings = $this->getOption($option->key);

			if (empty($optionSettings['default']) && $optionSettings !== 0) {
				$this->_warn('no value given for ' . $option->raw);
				$this[$option->key] = null;
			} else {
				$this[$option->key] = $optionSettings['default'];
			}
			return true;
		}

		$values = array();

		foreach ($this->_lexer as $value) {
			array_push($values, $value->raw);

			if (!$this->_lexer->end() && !$this->_lexer->peek->isValue) {
				break;
			}
		}

		$this[$option->key] = join(' ', $values);
		return true;
	}
}
