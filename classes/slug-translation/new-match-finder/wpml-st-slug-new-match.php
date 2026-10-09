<?php

class WPML_ST_Slug_New_Match {
	private $value;

	private $preserve_original;

	public function __construct( $value, $preserve_original ) {
		$this->value             = $value;
		$this->preserve_original = $preserve_original;
	}

	public function get_value() {
		return $this->value;
	}

	public function should_preserve_original() {
		return $this->preserve_original;
	}
}
