<?php

namespace WPML\ST\Batch\Translation;

use WPML\FP\Lst;
use WPML\FP\Fns;
use WPML\FP\Obj;

class Status {

	public static function add( array $translations, $languages ) {
		global $wpdb;

		$batches  = Records::findBatches( $wpdb, array_keys( $translations ) );
		$statuses = self::getStatuses( $wpdb, $batches );

		foreach ( $translations as $id => $string ) {
			foreach ( Obj::propOr( [], 'translations', $string ) as $lang => $data ) {
				$status = Obj::pathOr( null, [ $id, $lang ], $statuses );
				if ( $status ) {
					$translations[ $id ]['translations'][ $lang ]['status'] = $status;
				}
			}
		}

		return $translations;
	}

	public static function getStatuses( \wpdb $wpdb, $batches ) {
		$batchIds = array_unique( array_values( $batches ) );

		if ( $batchIds ) {
			$trids = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT element_id, trid FROM {$wpdb->prefix}icl_translations WHERE element_id IN (" . implode( ', ', array_fill( 0, count( $batchIds ), '%d' ) ) . ") AND element_type = 'st-batch_strings'",
					$batchIds
				)
			);

			$keyByBatchId = Fns::converge( Lst::zipObj(), [ Lst::pluck( 'element_id' ), Lst::pluck( 'trid' ) ] );

			$trids = $keyByBatchId( $trids );

			$transIds = [];
			if ( $trids ) {
				$transIds = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT translation_id, trid, language_code FROM {$wpdb->prefix}icl_translations WHERE trid IN (" . implode( ', ', array_fill( 0, count( $trids ), '%d' ) ) . ') AND source_language_code IS NOT NULL',
						$trids
					)
				);
			}

			$translationIds = Lst::pluck( 'translation_id', $transIds );
			$statuses       = [];
			if ( $translationIds ) {
				$statuses = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT status, translation_id FROM {$wpdb->prefix}icl_translation_status WHERE translation_id IN (" . implode( ', ', array_fill( 0, count( $translationIds ), '%d' ) ) . ')',
						$translationIds
					)
				);
			}

			$keyByTranslationId = Fns::converge(
				Lst::zipObj(),
				[
					Lst::pluck( 'translation_id' ),
					Lst::pluck( 'status' ),
				]
			);
			$statuses           = $keyByTranslationId( $statuses );

			$keyByTrid = Fns::converge( Lst::zipObj(), [ Lst::pluck( 'trid' ), Fns::identity() ] );

			return wpml_collect( $batches )
				->map( Obj::prop( Fns::__, $trids ) )
				->map( Obj::prop( Fns::__, $keyByTrid( $transIds ) ) )
				->map(
					function ( $item ) use ( $statuses ) {
						if ( ! is_object( $item ) ) {
							return [];
						}
						return [ Obj::prop( 'language_code', $item ) => Obj::prop( Obj::prop( 'translation_id', $item ), $statuses ) ];
					}
				)
				->toArray();
		} else {
			return [];
		}
	}

	public static function getStatusesOfBatch( \wpdb $wpdb, $batchId ) {
		$statuses = self::getStatuses( $wpdb, [ $batchId ] );

		return count( $statuses ) ? current( $statuses ) : [];
	}
}
