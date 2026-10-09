<?php

class WPML_ST_String_Translation_Priority_AJAX implements IWPML_Action {

	private $wpdb;

	public function __construct( wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	public function add_hooks() {
		\WPML\Request\Adapter\Ajax::register( 'wpml_change_string_translation_priority', \WPML\Request\Policy\Policy::capability( [ 'wpml_manage_string_translation', 'manage_translations' ], \WPML\Request\Policy\Authenticity::actionNonce( 'wpml_change_string_translation_priority_nonce', 'wpnonce' ) ), array( $this, 'change_string_translation_priority' ) );
	}

	public function change_string_translation_priority() {

		if ( ! current_user_can( 'wpml_manage_string_translation' ) && ! current_user_can( 'manage_translations' ) ) {
			/* translators: Error message shown when the user does not have the rights to do what they asked for. Past participle used as a state, lower case in the source. */
			wp_send_json_error( __( 'not allowed', 'wpml-string-translation' ) );
			return;
		}

		if ( $this->verify_ajax( 'wpml_change_string_translation_priority_nonce' ) ) {

			check_ajax_referer( 'wpml_change_string_translation_priority_nonce', 'wpnonce' );

			$strings = \WPML\Request\Payload::listField(
				$_POST,
				'strings',
				/* translators: Shown on the String Translation screen when a bulk change (target language or translation priority) is chosen with no strings selected. */
				__( 'Select the strings you want to change first.', 'wpml-string-translation' )
			);
			if ( \WPML\Request\Payload::isRefusal( $strings ) ) {
				\WPML\Request\Payload::refuse( $strings );

				return;
			}

			$change_string_translation_priority_dialog = new WPML_Strings_Translation_Priority( $this->wpdb );

			$string_ids = array_map( 'intval', $strings );
			$priority   = (string) filter_var( isset( $_POST['priority'] ) ? $_POST['priority'] : '', FILTER_SANITIZE_SPECIAL_CHARS );
			$change_string_translation_priority_dialog->change_translation_priority_of_strings( $string_ids, $priority );

			wp_send_json_success();
		}
	}

	private function verify_ajax( $ajax_action ) {
		return isset( $_POST['wpnonce'] ) && wp_verify_nonce( $_POST['wpnonce'], $ajax_action );
	}
}
