<?php

namespace WPML\ST\StringsCleanup;

class UntranslatedStrings {

	private $wpdb;

	public function __construct( \wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	public function getCountInDomains( $domains, $includeWaitingForTranslation = false ) {
		if ( ! $domains ) {
			return 0;
		}
		$wpdb = $this->wpdb;

		if ( $includeWaitingForTranslation ) {
			return (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT count(s.id) FROM {$wpdb->prefix}icl_strings AS s WHERE (s.status = %d OR (s.status = %d"
					. " AND NOT EXISTS (SELECT 1 FROM {$wpdb->prefix}icl_string_translations AS st WHERE st.string_id = s.id AND st.value IS NOT NULL)))"
					. ' AND s.context IN (' . implode( ', ', array_fill( 0, count( $domains ), '%s' ) ) . ')',
					...array_merge( [ ICL_TM_NOT_TRANSLATED, ICL_TM_WAITING_FOR_TRANSLATOR ], array_values( $domains ) )
				)
			);
		}

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT count(s.id) FROM {$wpdb->prefix}icl_strings AS s WHERE s.status = %d AND s.context IN ("
				. implode( ', ', array_fill( 0, count( $domains ), '%s' ) ) . ')'
				. " AND NOT EXISTS (SELECT 1 FROM {$wpdb->prefix}icl_string_translations AS st WHERE st.string_id = s.id AND st.status IN ("
				. implode( ', ', array_fill( 0, count( StringsWaitingForTranslation::statuses() ), '%d' ) ) . '))',
				...array_merge( [ ICL_TM_NOT_TRANSLATED ], array_values( $domains ), StringsWaitingForTranslation::statuses() )
			)
		);
	}

	public function getFromDomains( $domains, $batchSize, $includeWaitingForTranslation = false ) {
		if ( ! $domains ) {
			return [];
		}
		$wpdb = $this->wpdb;

		if ( $includeWaitingForTranslation ) {
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT s.id FROM {$wpdb->prefix}icl_strings AS s WHERE (s.status = %d OR (s.status = %d"
					. " AND NOT EXISTS (SELECT 1 FROM {$wpdb->prefix}icl_string_translations AS st WHERE st.string_id = s.id AND st.value IS NOT NULL)))"
					. ' AND s.context IN (' . implode( ', ', array_fill( 0, count( $domains ), '%s' ) ) . ')'
					. ' ORDER BY id LIMIT 0, %d',
					...array_merge( [ ICL_TM_NOT_TRANSLATED, ICL_TM_WAITING_FOR_TRANSLATOR ], array_values( $domains ), [ $batchSize ] )
				)
			);
		}

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT s.id FROM {$wpdb->prefix}icl_strings AS s WHERE s.status = %d AND s.context IN ("
				. implode( ', ', array_fill( 0, count( $domains ), '%s' ) ) . ')'
				. " AND NOT EXISTS (SELECT 1 FROM {$wpdb->prefix}icl_string_translations AS st WHERE st.string_id = s.id AND st.status IN ("
				. implode( ', ', array_fill( 0, count( StringsWaitingForTranslation::statuses() ), '%d' ) ) . '))'
				. ' ORDER BY id LIMIT 0, %d',
				...array_merge( [ ICL_TM_NOT_TRANSLATED ], array_values( $domains ), StringsWaitingForTranslation::statuses(), [ $batchSize ] )
			)
		);
	}

	public function remove( $stringIds ) {
		if ( ! $stringIds ) {
			return 0;
		}

		wpml_unregister_string_multi( $stringIds );

		$wpdb      = $this->wpdb;
		$remaining = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(id) FROM {$wpdb->prefix}icl_strings WHERE id IN ("
				. implode( ', ', array_fill( 0, count( $stringIds ), '%d' ) ) . ')',
				...array_values( $stringIds )
			)
		);

		return count( $stringIds ) - $remaining;
	}
}
