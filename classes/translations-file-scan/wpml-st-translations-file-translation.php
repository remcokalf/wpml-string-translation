<?php

class WPML_ST_Translations_File_Translation {
	private $original;

	private $translation;

	private $context;

	public function __construct( $original, $translation, $context = '' ) {
		$this->original    = $original;
		$this->translation = $translation;
		$this->context     = $context;
	}

	public function get_original() {
		return $this->original;
	}

	public function get_translation() {
		return $this->translation;
	}

	public function get_context() {
		return $this->context;
	}
}
