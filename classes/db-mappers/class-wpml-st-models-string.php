<?php

class WPML_ST_Models_String {
	private $language;

	private $domain;

	private $context;

	private $value;

	private $status;

	private $name;

	private $domain_name_context_md5;

	public function __construct( $language, $domain, $context, $value, $status, $name = null ) {
		$this->language = (string) $language;
		$this->domain   = (string) $domain;
		$this->context  = (string) $context;
		$this->value    = (string) $value;
		$this->status   = (int) $status;

		if ( ! $name ) {
			$name = md5( $value );
		}
		$this->name     = (string) $name;

		$this->domain_name_context_md5 = md5( $domain . $name . $context );
	}

	public function get_language() {
		return $this->language;
	}

	public function get_domain() {
		return $this->domain;
	}

	public function get_context() {
		return $this->context;
	}

	public function get_value() {
		return $this->value;
	}

	public function get_status() {
		return $this->status;
	}

	public function get_name() {
		return $this->name;
	}

	public function get_domain_name_context_md5() {
		return $this->domain_name_context_md5;
	}
}