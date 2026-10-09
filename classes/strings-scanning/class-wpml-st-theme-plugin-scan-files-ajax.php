<?php

class WPML_ST_Theme_Plugin_Scan_Files_Ajax implements IWPML_Action {

	private $string_scanner;

	public function __construct( IWPML_ST_String_Scanner $string_scanner ) {
		$this->string_scanner = $string_scanner;
	}

	public function add_hooks() {
		\WPML\Request\Adapter\Ajax::register( 'wpml_st_scan_chunk', \WPML\Request\Policy\Policy::capability( 'wpml_manage_theme_and_plugin_localization', \WPML\Request\Policy\Authenticity::actionNonce( 'wpml_st_scan_chunk', 'nonce' ) ), array( $this, 'scan' ) );
	}

	public function scan() {
		if ( ! current_user_can( 'wpml_manage_theme_and_plugin_localization' ) ) {
			/* translators: Error message shown when the user does not have the rights to do what they asked for. Past participle used as a state, lower case in the source. */
			wp_send_json_error( __( 'not allowed', 'wpml-string-translation' ) );
			return;
		}

		$this->string_scanner->scan();
	}
}
