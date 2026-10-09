<?php

namespace WPML\ST\StringsFilter;

class Translator {
	private $language;

	private $translationReceiver;

	private $translations;

	public function __construct(
		$language,
		TranslationReceiver $translationReceiver
	) {
		$this->language            = $language;
		$this->translationReceiver = $translationReceiver;
	}

	public function translate( StringEntity $string ) {
		if ( $this->translations === null ) {
			$this->translations = new Translations();
		}

		$translation = $this->translations->get( $string );
		if ( ! $translation ) {
			$translation = $this->translationReceiver->get( $string, $this->language );
			$this->translations->add( $string, $translation );
		}

		return $translation;
	}
}
