<?php

namespace WPML\ST\Batch\Translation;

use WPML\FP\Curryable;
use WPML\FP\Fns;
use WPML\FP\Lst;
use function WPML\Container\make;
use function WPML\FP\curryN;

class Records {

	use Curryable;

	public static function get( ?\wpdb $wpdb = null, $batchId = null ) {
		return call_user_func_array(
			curryN(
			2,
			function ( \wpdb $wpdb, $batchId ) {
				return $wpdb->get_col( $wpdb->prepare( "SELECT string_id FROM {$wpdb->prefix}icl_string_batches WHERE batch_id = %d", $batchId ) );
				}
			),
			func_get_args()
		);
	}

}

Records::curryN(
	'installSchema',
	1,
	function ( \wpdb $wpdb ) {
		$option = make( 'WPML\WP\OptionManager' );
		if ( ! $option->get( 'ST', Records::class . '_schema_installed' ) ) {
			$wpdb->query(
				"CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}icl_string_batches` (
					`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
					`string_id` bigint(20) unsigned NOT NULL,
					`batch_id` bigint(20) unsigned NOT NULL,
					PRIMARY KEY (`id`)
				) " . $wpdb->get_charset_collate()
			);
			$option->set( 'ST', Records::class . '_schema_installed', true );
		}
	}
);

Records::curryN(
	'set',
	3,
	function ( \wpdb $wpdb, $batchId, $stringId ) {
		$wpdb->insert(
			"{$wpdb->prefix}icl_string_batches",
			[
				'batch_id'  => $batchId,
				'string_id' => $stringId,
			],
			[ '%d', '%d' ]
		);
	}
);

Records::curryN(
	'findBatch',
	2,
	function ( \wpdb $wpdb, $stringId ) {
		return $wpdb->get_var( $wpdb->prepare( "SELECT batch_id FROM {$wpdb->prefix}icl_string_batches WHERE string_id = %d", $stringId ) );
	}
);

Records::curryN(
	'findBatches',
	2,
	function ( \wpdb $wpdb, $stringIds ) {
		$data = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT batch_id, string_id FROM {$wpdb->prefix}icl_string_batches WHERE string_id IN (" . implode( ', ', array_fill( 0, count( $stringIds ), '%d' ) ) . ')',
				$stringIds
			)
		);

		$keyByStringId = Fns::converge( Lst::zipObj(), [ Lst::pluck( 'string_id' ), Lst::pluck( 'batch_id' ) ] );

		return $keyByStringId( $data );
	}
);
