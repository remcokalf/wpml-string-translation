<?php

class WPML_ST_Taxonomy_Strings {

	const CONTEXT_GENERAL  = 'taxonomy general name';
	const CONTEXT_SINGULAR = 'taxonomy singular name';

	const LEGACY_NAME_PREFIX_GENERAL  = 'taxonomy general name: ';
	const LEGACY_NAME_PREFIX_SINGULAR = 'taxonomy singular name: ';
	const LEGACY_STRING_DOMAIN        = 'WordPress';

	private $slug_translation_records;

	private $string_factory;

	private $translated_with_gettext_context = array();

	public function __construct(
		WPML_Tax_Slug_Translation_Records $slug_translation_records,
		WPML_ST_String_Factory $string_factory
	) {
		$this->slug_translation_records = $slug_translation_records;
		$this->string_factory           = $string_factory;
	}

	public function add_to_translated_with_gettext_context( $text, $domain ) {
		if ( ! in_array( $text, $this->translated_with_gettext_context, true ) ) {
			$this->translated_with_gettext_context[ $text ] = $domain;
		}
	}

	private function find_string_id( $text, $gettext_context = '', $domain = '', $name = false ) {
		$context = $this->get_context( $domain, $gettext_context );
		return $this->string_factory->get_string_id( $text, $context, $name );
	}

	public function create_string_if_not_exist( $text, $gettext_context = '', $domain = '', $name = false ) {
		$source_lang = apply_filters( 'wpml_taxonomy_strings_source_language', null, $text, $name );

		if ( null === $source_lang ) {
			$source_lang = $this->get_default_language();
		}

		$string_id = $this->find_string_id( $text, $gettext_context, $domain, $name );

		if ( ! $string_id ) {
			$context   = $this->get_context( $domain, $gettext_context );
			$string_id = icl_register_string( $context, (string) $name, $text, false, $source_lang );
		}
		return $string_id;

	}

	private function get_context( $domain, $gettext_context ) {
		return array(
			'domain'  => $domain,
			'context' => $gettext_context,
		);
	}

	private function get_default_language() {
		global $sitepress;

		return $sitepress ? (string) $sitepress->get_default_language() : '';
	}

	public function get_taxonomy_strings( $taxonomy_name, $register = true ) {
		$taxonomy = get_taxonomy( $taxonomy_name );

		if ( $taxonomy && isset( $taxonomy->label ) && isset( $taxonomy->labels->singular_name ) ) {
			$general_string  = $this->get_label_string( $taxonomy->label, 'general', $register );
			$singular_string = $this->get_label_string( $taxonomy->labels->singular_name, 'singular', $register );
			$slug_string     = $this->get_slug_string( $taxonomy, $register );

			return array( $general_string, $singular_string, $slug_string );
		}

		return null;
	}

	private function get_label_string( $value, $general_or_singular, $register = true ) {
		$string    = $this->get_label_string_details( $value, $general_or_singular );
		$string_id = $this->find_string_id( $value, $string['context'], $string['domain'], $string['name'] );

		if ( ! $string_id && $register ) {
			$string_id = $this->create_string_if_not_exist( $value, $string['context'], $string['domain'], $string['name'] );
		}

		if ( $string_id ) {
			return $this->string_factory->find_by_id( $string_id );
		}

		return null;
	}

	private function get_slug_string( $taxonomy, $register = true ) {
		$string_id = $this->slug_translation_records->get_slug( $taxonomy->name )->get_original_id();

		if ( ! $string_id ) {
			if ( ! $register ) {
				return null;
			}

			$slug      = isset( $taxonomy->rewrite['slug'] ) ? trim( $taxonomy->rewrite['slug'], '/' ) : $taxonomy->name;
			$string_id = $this->slug_translation_records->register_slug( $taxonomy->name, $slug );
		}

		return $this->string_factory->find_by_id( $string_id );
	}

	private function get_label_string_details( $value, $general_or_singular ) {
		$string_meta = array(
			'context' => '',
			'domain'  => '',
			'name'    => false,
		);

		if ( $this->is_string_translated_with_gettext_context( $value ) ) {
			$string_meta['domain']  = $this->get_domain_for_taxonomy( $value );
			$string_meta['context'] = self::CONTEXT_SINGULAR;

			if ( 'general' === $general_or_singular ) {
				$string_meta['context'] = self::CONTEXT_GENERAL;
			}
		} else {
			$string_meta['domain'] = self::LEGACY_STRING_DOMAIN;
			$string_meta['name']   = self::LEGACY_NAME_PREFIX_SINGULAR . $value;

			if ( 'general' === $general_or_singular ) {
				$string_meta['name'] = self::LEGACY_NAME_PREFIX_GENERAL . $value;
			}
		}

		return $string_meta;
	}

	private function is_string_translated_with_gettext_context( $value ) {
		return array_key_exists( $value, $this->translated_with_gettext_context );
	}

	private function get_domain_for_taxonomy( $value ) {
		return $this->translated_with_gettext_context[ $value ];
	}
}
