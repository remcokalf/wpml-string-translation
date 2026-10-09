<?php

namespace WPML\ST\StringsFilter;

class TranslationEntity {
	private $value;

	private $hasTranslation;

	private $stringRegistered;

	public function __construct( $value, $hasTranslation, $stringRegistered = true ) {
		$this->value            = $value;
		$this->hasTranslation   = $hasTranslation;
		$this->stringRegistered = $stringRegistered;
	}

	public function getValue() {
		return $this->value;
	}

	public function isStringRegistered() {
		return $this->stringRegistered;
	}

	public function hasTranslation() {
		return $this->hasTranslation;
	}
}
