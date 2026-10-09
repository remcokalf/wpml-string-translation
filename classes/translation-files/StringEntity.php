<?php

namespace WPML\ST\TranslationFile;

class StringEntity {

	private $original;

	private $translations = array();

	private $context;

	private $original_plural;

	private $name;

	public function __construct( $original, array $translations, $context = null, $original_plural = null, $name = null ) {
		$this->original        = $original;
		$this->translations    = $translations;
		$this->context         = $context ? $context : null;
		$this->original_plural = $original_plural;
		$this->name            = $name;
	}

	public function get_original() {
		return $this->original;
	}

	public function get_translations() {
		return $this->translations;
	}

	public function get_context() {
		return $this->context;
	}

	public function get_original_plural() {
		return $this->original_plural;
	}

	public function get_name() {
		return $this->name;
	}

	public function set_name( $name ) {
		$this->name = $name;
	}
}
