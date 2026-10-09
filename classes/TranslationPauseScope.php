<?php

namespace WPML\ST;

class TranslationPauseScope {

	const CORE_READ_MODEL = '\WPML\LanguageEditor\TranslationPause';

	public static function translatable( array $codes ) {
		if ( class_exists( self::CORE_READ_MODEL ) ) {
			return \WPML\LanguageEditor\TranslationPause::filterTranslatable( $codes );
		}

		return array_values( $codes );
	}
}
