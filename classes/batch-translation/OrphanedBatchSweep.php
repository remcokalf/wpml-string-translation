<?php

namespace WPML\ST\Batch\Translation;

class OrphanedBatchSweep {

	const TTL = 15 * MINUTE_IN_SECONDS;

	const MAX_ROWS_PER_PASS = 200;

	private $wpdb;

	public function __construct( \wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	public function run() {
		$ttl = (int) apply_filters( 'wpml_st_orphaned_batch_ttl', self::TTL );

		if ( $ttl < 1 ) {
			return 0;
		}

		$rows = $this->findStranded( $ttl, self::MAX_ROWS_PER_PASS );

		if ( ! $rows ) {
			return 0;
		}

		$this->resetToUntranslated( \wp_list_pluck( $rows, 'id' ) );

		\do_action( 'wpml_st_orphaned_batch_strings_recovered', $rows );

		return count( $rows );
	}

	private function findStranded( $ttl, $limit ) {
		$sql = "
			SELECT st.id, st.string_id, st.language
			FROM {$this->wpdb->prefix}icl_string_translations st
			WHERE st.status = %d
				AND st.translation_date < DATE_SUB( NOW(), INTERVAL %d SECOND )
				-- The 7665 parking shape provably ENTERED the batch flow: layer 5
				-- marks WAITING at batch creation, so a batch row exists by the
				-- time the job creation can raise. A WAITING string with no batch
				-- row of any kind belongs to another flow entirely (legacy
				-- pre-batch rows on upgraded sites, still awaiting a translator)
				-- and is not this sweep's business - without this guard the NOT
				-- EXISTS below is vacuously true for every one of them.
				AND EXISTS (
					SELECT 1
					FROM {$this->wpdb->prefix}icl_string_batches entered
					WHERE entered.string_id = st.string_id
				)
				AND NOT EXISTS (
					SELECT 1
					FROM {$this->wpdb->prefix}icl_string_batches batch
					INNER JOIN {$this->wpdb->prefix}icl_translations batch_original
						ON batch_original.element_id = batch.batch_id
						AND batch_original.element_type = 'st-batch_strings'
						AND batch_original.source_language_code IS NULL
					INNER JOIN {$this->wpdb->prefix}icl_translations batch_target
						ON batch_target.trid = batch_original.trid
						AND batch_target.source_language_code IS NOT NULL
					INNER JOIN {$this->wpdb->prefix}icl_translation_status batch_status
						ON batch_status.translation_id = batch_target.translation_id
					-- Both correlations live in the WHERE on purpose: MySQL does
					-- not resolve an outer reference inside a subquery ON clause.
					WHERE batch.string_id = st.string_id
						AND batch_target.language_code = st.language
				)
			ORDER BY st.id
			LIMIT %d
		";

		return (array) $this->wpdb->get_results(
			$this->wpdb->prepare(
				$sql,
				ICL_TM_WAITING_FOR_TRANSLATOR,
				(int) $ttl,
				(int) $limit
			)
		);
	}

	private function resetToUntranslated( array $ids ) {
		$ids = array_values( array_filter( array_map( 'intval', $ids ) ) );

		if ( ! $ids ) {
			return;
		}

		$placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );

		$this->wpdb->query(
			$this->wpdb->prepare(
				"UPDATE {$this->wpdb->prefix}icl_string_translations
					SET status = %d
					WHERE id IN ( {$placeholders} )",
				array_merge( [ ICL_TM_NOT_TRANSLATED ], $ids )
			)
		);
	}
}
