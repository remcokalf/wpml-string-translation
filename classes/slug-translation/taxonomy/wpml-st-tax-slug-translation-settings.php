<?php

class WPML_ST_Tax_Slug_Translation_Settings extends WPML_ST_Slug_Translation_Settings {

	const OPTION_NAME = 'wpml_tax_slug_translation_settings';

	private $types = array();

	public function __construct() {
		$this->init();
	}

	public function set_types( array $types ) {
		$this->types = $types;
	}

	public function get_types() {
		return $this->types;
	}

	public function is_translated( $taxonomy_name ) {
		return array_key_exists( $taxonomy_name, $this->types ) && (bool) $this->types[ $taxonomy_name ];
	}

	public function set_type( $taxonomy_name, $is_enabled ) {
		$this->types[ $taxonomy_name ] = (int) $is_enabled;
	}

	private function get_properties() {
		return get_object_vars( $this );
	}

	public function init() {
		$options = get_option( self::OPTION_NAME, array() );

		foreach ( $this->get_properties() as $name => $value ) {
			$callback = array( $this, 'set_' . $name );
			if ( array_key_exists( $name, $options ) && is_callable( $callback ) ) {
				call_user_func( $callback, $options[ $name ] );
			}
		}
	}

	public function save() {
		update_option( self::OPTION_NAME, $this->get_properties() );
	}
}
