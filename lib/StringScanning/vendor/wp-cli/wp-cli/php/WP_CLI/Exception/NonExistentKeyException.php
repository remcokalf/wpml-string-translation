<?php

namespace WP_CLI\Exception;

use OutOfBoundsException;
use WP_CLI\Traverser\RecursiveDataStructureTraverser;

class NonExistentKeyException extends OutOfBoundsException {
	protected $traverser;

	public function set_traverser( $traverser ) {
		$this->traverser = $traverser;
	}

	public function get_traverser() {
		return $this->traverser;
	}
}
