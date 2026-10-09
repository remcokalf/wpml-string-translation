<?php

class WPML_ST_Settings {
	const SETTINGS_KEY = 'icl_st_settings';

	private $settings = null;

	private $updated_settings = array();

	public function get_settings() {
		if ( ! $this->settings ) {
			$options        = get_option( self::SETTINGS_KEY );
			$this->settings = is_array( $options ) ? $options : array();
		}

		return array_merge( $this->settings, $this->updated_settings );
	}

	public function get_setting( $name ) {
		$this->get_settings();

		return isset( $this->settings[ $name ] ) ? $this->settings[ $name ] : null;
	}

	public function update_setting( $key, $value, $save = false ) {
		$this->get_settings();

		$this->updated_settings[ $key ] = $value;

		if ( $save ) {
			$this->save_settings();
		}
	}

	public function delete_settings() {
		delete_option( self::SETTINGS_KEY );
		delete_option( 'wpml_pb_has_shortcode_settings' );
	}

	public function save_settings() {
		$settings = $this->get_settings();

		update_option( self::SETTINGS_KEY, $settings, false );
		do_action( 'icl_save_settings', $this->updated_settings );

		$this->updated_settings = array();
		$this->settings         = $settings;
	}
}
