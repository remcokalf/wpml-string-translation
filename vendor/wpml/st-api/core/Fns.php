<?php

namespace WPML\ST\API;

use WPML\Collect\Support\Traits\Macroable;
use function WPML\FP\curryN;

class Fns {

	use Macroable;

	public static function init() {

		self::macro( 'saveTranslation', curryN( 4, 'icl_add_string_translation' ) );

		self::macro( 'getTranslation', curryN( 2, 'icl_get_string_by_id' ) );

		self::macro( 'getTranslations', curryN( 1, 'icl_get_string_translations_by_id' ) );

		self::macro( 'updateStatus', curryN( 3, function ( $stringId, $language, $status ) {
			return self::saveTranslation( $stringId, $language, null, $status );
		} ) );

		self::macro( 'getStringTranslationById', curryN( 1, function ( $stringTranslationId ) {
			global $wpdb;

			return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}icl_string_translations WHERE id = %d", $stringTranslationId ) );
		} ) );

		self::macro( 'getStringById', curryN( 1, function ( $stringId ) {
			global $wpdb;

			return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}icl_strings WHERE id = %d", $stringId ) );
		} ) );
	}
}

Fns::init();
