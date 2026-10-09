<?php

class WPML_ST_Theme_String_Scanner_Factory {

	public function create() {
		return new WPML_Theme_String_Scanner( wpml_get_filesystem() );
	}
}
