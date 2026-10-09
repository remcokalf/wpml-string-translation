<?php

namespace WPML\ST\DB\Mappers;

use WPML\FP\Curryable;

class DomainsRepository {
	use Curryable;

	public static function init() {
		self::curryN( 'getByStringIds', 1, function ( array $stringIds ) {
			global $wpdb;

			$sql = "SELECT DISTINCT `context` FROM {$wpdb->prefix}icl_strings WHERE id IN (" . wpml_prepare_in( $stringIds ) . ")";

			return $wpdb->get_col( $sql );
		} );
	}
}

DomainsRepository::init();