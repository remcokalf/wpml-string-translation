<?php

class WPML_ST_Privacy_Content extends WPML_Privacy_Content {

	protected function get_plugin_name() {
		return 'WPML String Translation';
	}

	protected function get_privacy_policy() {
		return __( 'WPML String Translation will send all strings to WPML’s <b>Advanced Translation Editor</b> and to the translation services which are used.', 'wpml-string-translation' );
	}

}
