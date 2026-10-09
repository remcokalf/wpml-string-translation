<?php

namespace WPML\ST;

use WPML_WP_API;

class StringTranslationPage {

	public static function isCurrent() {
		return ( new WPML_WP_API() )->is_string_translation_page();
	}

}
