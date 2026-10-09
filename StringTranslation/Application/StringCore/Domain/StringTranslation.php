<?php

namespace WPML\StringTranslation\Application\StringCore\Domain;

class StringTranslation {

	private $string;

	private $language;

	private $value;

	public function __construct(
		string $language,
		string $value,
		?StringItem $string = null
	) {
		$this->setString( $string );
		$this->setLanguage( $language );
		$this->setValue( $value );
	}

	public function setString( ?StringItem $string ) {
		$this->string = $string;
	}

	public function getString(): ?StringItem {
		return $this->string;
	}

	public function getLanguage(): string {
		return $this->language;
	}

	public function setLanguage( string $language ) {
		$this->language = $language;
	}

	public function getValue(): string {
		return $this->value;
	}

	public function setValue( string $value ) {
		$this->value = $value;
	}
}

