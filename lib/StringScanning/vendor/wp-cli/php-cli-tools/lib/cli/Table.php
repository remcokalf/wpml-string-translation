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

use cli\Shell;
use cli\Streams;
use cli\table\Ascii;
use cli\table\Renderer;
use cli\table\Tabular;

class Table {
	protected $_renderer;
	protected $_headers = array();
	protected $_footers = array();
	protected $_width = array();
	protected $_rows = array();

	public function __construct(array $headers = array(), array $rows = array(), array $footers = array()) {
		if (!empty($headers)) {
			if ($rows === array()) {
				$rows = $headers;
				$keys = array_keys(array_shift($headers));
				$headers = array();

				foreach ($keys as $header) {
					$headers[$header] = $header;
				}
			}

			$this->setHeaders($headers);
			$this->setRows($rows);
		}

		if (!empty($footers)) {
			$this->setFooters($footers);
		}

		if (Shell::isPiped()) {
			$this->setRenderer(new Tabular());
		} else {
			$this->setRenderer(new Ascii());
		}
	}

	public function resetTable()
	{
		$this->_headers = array();
		$this->_width = array();
		$this->_rows = array();
		$this->_footers = array();
		return $this;
	}

	public function setRenderer(Renderer $renderer) {
		$this->_renderer = $renderer;
	}

	protected function checkRow(array $row) {
		foreach ($row as $column => $str) {
			$width = Colors::width( $str, $this->isAsciiPreColorized( $column ) );
			if (!isset($this->_width[$column]) || $width > $this->_width[$column]) {
				$this->_width[$column] = $width;
			}
		}

		return $row;
	}

	public function display() {
		foreach( $this->getDisplayLines() as $line ) {
			Streams::line( $line );
		}
	}

	public function getDisplayLines() {
		$this->_renderer->setWidths($this->_width, $fallback = true);
		$border = $this->_renderer->border();

		$out = array();
		if (isset($border)) {
			$out[] = $border;
		}
		$out[] = $this->_renderer->row($this->_headers);
		if (isset($border)) {
			$out[] = $border;
		}

		foreach ($this->_rows as $row) {
			$row = $this->_renderer->row($row);
			$row = explode( PHP_EOL, $row );
			$out = array_merge( $out, $row );
		}

		if (isset($border)) {
			$out[] = $border;
		}

		if ($this->_footers) {
			$out[] = $this->_renderer->row($this->_footers);
			if (isset($border)) {
				$out[] = $border;
			}
		}
		return $out;
	}

	public function sort($column) {
		if (!isset($this->_headers[$column])) {
			trigger_error('No column with index ' . $column, E_USER_NOTICE);
			return;
		}

		usort($this->_rows, function($a, $b) use ($column) {
			return strcmp($a[$column], $b[$column]);
		});
	}

	public function setHeaders(array $headers) {
		$this->_headers = $this->checkRow($headers);
	}

	public function setFooters(array $footers) {
		$this->_footers = $this->checkRow($footers);
	}


	public function addRow(array $row) {
		$this->_rows[] = $this->checkRow($row);
	}

	public function setRows(array $rows) {
		$this->_rows = array();
		foreach ($rows as $row) {
			$this->addRow($row);
		}
	}

	public function countRows() {
		return count($this->_rows);
	}

	public function setAsciiPreColorized( $pre_colorized ) {
		if ( $this->_renderer instanceof Ascii ) {
			$this->_renderer->setPreColorized( $pre_colorized );
		}
	}

	private function isAsciiPreColorized( $column ) {
		if ( $this->_renderer instanceof Ascii ) {
			return $this->_renderer->isPreColorized( $column );
		}
		return false;
	}
}
