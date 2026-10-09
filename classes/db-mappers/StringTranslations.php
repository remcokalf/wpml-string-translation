<?php

namespace WPML\ST\DB\Mappers;

use function WPML\FP\curryN;

class StringTranslations {
	public static function hasTranslation( $wpdb = null, $stringId = null, $language = null ) {
		$has = function ( \wpdb $wpdb, $stringId, $language ) {
			return $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(id) FROM {$wpdb->prefix}icl_string_translations WHERE string_id = %d AND language = %s",
					$stringId,
					$language
				)
			) > 0;
		};

		return call_user_func_array( curryN( 3, $has ), func_get_args() );
	}

}
