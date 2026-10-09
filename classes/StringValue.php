<?php

namespace WPML\ST;

class StringValue {

	const READY_OPTION = 'wpml_st_has_text_backfilled';

	private static $ready = null;

	public static function hasText( $value ) {
		return '' !== trim( (string) $value, ' ' );
	}

	public static function hasTextFlag( $value ) {
		return self::hasText( $value ) ? 1 : 0;
	}

	public static function isReady() {
		if ( null === self::$ready ) {
			self::$ready = (bool) get_option( self::READY_OPTION );
		}

		return self::$ready;
	}

	public static function resetReadyCache() {
		self::$ready = null;
	}
}
