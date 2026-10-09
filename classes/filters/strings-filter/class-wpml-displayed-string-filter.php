<?php

use WPML\ST\StringsFilter\Translator;
use WPML\ST\StringsFilter\StringEntity;
use WPML\ST\StringsFilter\TranslationEntity;

class WPML_Displayed_String_Filter {
	protected $translator;

	public function __construct( Translator $translator ) {
		$this->translator = $translator;
	}

	public function translate_by_name_and_context(
		$untranslated_text,
		$name,
		$context = '',
		&$has_translation = null
	) {
		if ( is_array( $untranslated_text ) || is_object( $untranslated_text ) ) {
			return '';
		}

		$translation = $this->get_translation( $untranslated_text, $name, $context );

		$has_translation = $translation->hasTranslation();

		return $translation->getValue();
	}

	protected function transform_parameters( $name, $context ) {
		list ( $domain, $gettext_context ) = wpml_st_extract_context_parameters( $context );

		return array( $name, $domain, $gettext_context );
	}

	public static function truncate_long_string( $string ) {
		if ( strlen( $string ) <= WPML_STRING_TABLE_NAME_CONTEXT_LENGTH ) {
			return $string;
		}

		return md5( $string );
	}

	protected function get_translation( $untranslated_text, $name, $context ) {
		list ( $name, $domain, $gettext_context ) = $this->transform_parameters( $name, $context );
		$untranslated_text                        = is_numeric( $untranslated_text ) ? (string) $untranslated_text : $untranslated_text;

		$translation = $this->translator->translate(
			new StringEntity(
				$untranslated_text,
				$name,
				$domain,
				$gettext_context
			)
		);

		if ( ! $translation->hasTranslation() ) {
			list( $name, $domain ) = array_map( array( $this, 'truncate_long_string' ), array( $name, $domain ) );
			$translation           = $this->translator->translate(
				new StringEntity(
					$untranslated_text,
					$name,
					$domain,
					$gettext_context
				)
			);
		}

		return $translation;
	}
}
