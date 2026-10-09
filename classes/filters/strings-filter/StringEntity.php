<?php

namespace WPML\ST\StringsFilter;

class StringEntity {
	private $value;

	private $name;

	private $domain;

	private $context;

	public function __construct( $value, $name, $domain, $context = '' ) {
		$this->value   = $value;
		$this->name    = $name;
		$this->domain  = $domain;
		$this->context = $context;
	}

	public function getValue() {
		return $this->value;
	}

	public function hasValue() {
		$value = $this->getValue();
		return is_string( $value ) && strlen( $value ) > 0;
	}

	public function getName() {
		return $this->name;
	}

	public function getDomain() {
		return $this->domain;
	}

	public function getContext() {
		return $this->context;
	}

	public static function fromArray( array $data ) {
		return new self( $data['value'], $data['name'], $data['domain'], $data['context'] );
	}
}
