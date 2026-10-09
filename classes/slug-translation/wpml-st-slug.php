<?php

class WPML_ST_Slug {

	private $original_id;

	private $original_lang;

	private $original_value;

	private $langs = array();

	public function set_lang_data( stdClass $data ) {
		if ( isset( $data->language ) ) {
			$this->langs[ $data->language ] = $data;
		}

		if ( isset( $data->id ) ) {
			$this->original_id    = $data->id;
			$this->original_lang  = $data->language;
			$this->original_value = $data->value;
		}
	}

	public function to_array() {
		$rows = array();

		foreach ( $this->langs as $lang => $data ) {
			$rows[ $lang ] = (array) $data;
		}

		return $rows;
	}

	public static function from_array( array $rows ) {
		$slug = new self();

		foreach ( $rows as $data ) {
			if ( is_array( $data ) ) {
				$slug->set_lang_data( (object) $data );
			}
		}

		return $slug;
	}

	public function get_original_lang() {
		return $this->original_lang;
	}

	public function get_original_value() {
		return $this->original_value;
	}

	public function get_original_id() {
		return (int) $this->original_id;
	}

	public function get_value( $lang ) {
		if ( isset( $this->langs[ $lang ]->value ) ) {
			return $this->langs[ $lang ]->value;
		}

		return $this->get_original_value();
	}

	public function get_status( $lang ) {
		if ( isset( $this->langs[ $lang ]->status ) ) {
			return (int) $this->langs[ $lang ]->status;
		}

		return ICL_TM_NOT_TRANSLATED;
	}

	public function is_translation_complete( $lang ) {
		return ICL_TM_COMPLETE === $this->get_status( $lang );
	}

	public function get_context() {
		if ( isset( $this->langs[ $this->original_lang ]->context ) ) {
			return $this->langs[ $this->original_lang ]->context;
		}

		return null;
	}

	public function get_name() {
		if ( isset( $this->langs[ $this->original_lang ]->name ) ) {
			return $this->langs[ $this->original_lang ]->name;
		}

		return null;
	}

	public function get_language_codes() {
		return array_keys( $this->langs );
	}

	public function filter_value( $slug_value, $lang ) {
		if ( $this->original_lang === $lang ) {
			return $this->original_value;
		} elseif ( in_array( $lang, $this->get_language_codes(), true ) && $this->is_translation_complete( $lang ) ) {
			return $this->get_value( $lang );
		}

		return $slug_value;
	}
}
