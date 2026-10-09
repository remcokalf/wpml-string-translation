<?php

namespace WPML\ST\StringTranslationUI;

use WPML\Language\RequestedLanguage;

class ConfiguredLanguages {

	public static function rows( \SitePress $sitepress ) {
		return array_intersect_key(
			(array) $sitepress->get_languages( $sitepress->get_admin_language() ),
			array_flip( RequestedLanguage::configured() )
		);
	}
}
