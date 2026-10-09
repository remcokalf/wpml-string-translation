<?php

namespace WPML\ST\StringsFilter;

class Translations {
	private $data;

	public function __construct() {
		$this->data = new TranslationsObjectStorage();
	}


	public function add( StringEntity $string, TranslationEntity $translation ) {
		$this->data->offsetSet( $string, $translation );
	}


	public function get( StringEntity $string ) {
		return $this->data->offsetExists( $string ) ? $this->data[ $string ] : null;
	}
}
