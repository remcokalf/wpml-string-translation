<?php

class WPML_ST_Records {

	public $wpdb;

	public function __construct( wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	public function get_wpdb() {
		return $this->wpdb;
	}

	public function icl_strings_by_string_id( $string_id ) {

		return new WPML_ST_ICL_Strings( $this->wpdb, $string_id );
	}

	public function icl_string_translations_by_string_id_and_language( $string_id, $language_code ) {

		return new WPML_ST_ICL_String_Translations( $this->wpdb, $string_id, $language_code );
	}
}
