<?php

class WPML_ST_Models_String_Translation {
	private $string_id;

	private $language;

	private $status;

	private $value;

	private $mo_string;

	public function __construct( $string_id, $language, $status, $value, $mo_string ) {
		$this->string_id = (int) $string_id;
		$this->language  = (string) $language;
		$this->status    = (int) $status;
		$this->value     = (string) $value;
		$this->mo_string = (string) $mo_string;
	}

	public function get_string_id() {
		return $this->string_id;
	}

	public function get_language() {
		return $this->language;
	}

	public function get_status() {
		return $this->status;
	}

	public function get_value() {
		return $this->value;
	}

	public function get_mo_string() {
		return $this->mo_string;
	}
}