<?php

class WPML_ST_String_Tracking_AJAX implements IWPML_Action {

	private $string_position;

	private $globals_validation;

	private $action;

	public function __construct(
		WPML_ST_String_Positions $string_position,
		WPML_Super_Globals_Validation $globals_validation,
		$action
	) {
		$this->string_position    = $string_position;
		$this->globals_validation = $globals_validation;
		$this->action             = $action;
	}

	public function add_hooks() {
		\WPML\Request\Adapter\Ajax::register( $this->action, \WPML\Request\Policy\Policy::capability( [ 'wpml_manage_string_translation', 'manage_translations' ], \WPML\Request\Policy\Authenticity::actionNonce( $this->action, 'nonce' ) ), array( $this, 'render_string_position' ) );
	}

	public function render_string_position() {
		$string_id = $this->globals_validation->get( 'string_id', FILTER_SANITIZE_NUMBER_INT );
		$this->string_position->dialog_render( $string_id );
		wp_die();
	}
}
