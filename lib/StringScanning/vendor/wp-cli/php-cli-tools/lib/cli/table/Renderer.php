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

namespace cli\table;

abstract class Renderer {
	protected $_widths = array();

	public function __construct(array $widths = array()) {
		$this->setWidths($widths);
	}

	public function setWidths(array $widths, $fallback = false) {
		if ($fallback) {
			foreach ( $this->_widths as $index => $value ) {
			    $widths[$index] = $value;
			}
		}
		$this->_widths = $widths;
	}

	public function border() {
		return null;
	}

	abstract public function row(array $row);
}
