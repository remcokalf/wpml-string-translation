<?php

namespace WPML\ST\DB\Mappers;

use \wpdb;

class StringsRetrieve {

	const CONTEXT_WORDPRESS = 'WordPress';
	const CONTEXT_DEFAULT   = 'default';

	const PAGE_SIZE = 1000;

	private $wpdb;

	private $page_size;

	public function __construct( wpdb $wpdb, $page_size = self::PAGE_SIZE ) {
		$this->wpdb      = $wpdb;
		$this->page_size = max( 1, (int) $page_size );
	}

	public function get( $language, $domain, $modified_mo_only = false ) {
		$rows = $this->getDomainRows( $language, $domain, $modified_mo_only );

		if ( self::CONTEXT_DEFAULT === strtolower( (string) $domain ) ) {
			$rows = array_merge(
				$rows,
				array_values(
					array_filter(
						$this->getDomainRows( $language, self::CONTEXT_WORDPRESS, $modified_mo_only ),
						[ self::class, 'isWordPressCoreRow' ]
					)
				)
			);
		}

		return $rows;
	}

	public static function isWordPressCoreRow( array $row ) {
		return isset( $row['name'], $row['original'] ) && md5( (string) $row['original'] ) === $row['name'];
	}

	private function getDomainRows( $language, $domain, $modified_mo_only ) {
		$rows   = [];
		$cursor = 0;

		do {
			$ids     = $this->getPageOfIds( $domain, $cursor );
			$fetched = count( $ids );

			if ( ! $fetched ) {
				break;
			}

			$rows   = array_merge( $rows, $this->getRowsForIds( $ids, $language, $domain, $modified_mo_only ) );
			$cursor = (int) end( $ids );
		} while ( $fetched === $this->page_size );

		return $rows;
	}

	private function getPageOfIds( $domain, $cursor ) {
		$ids = $this->wpdb->get_col( $this->wpdb->prepare( "SELECT id FROM {$this->wpdb->prefix}icl_strings WHERE context = %s AND id > %d ORDER BY id LIMIT %d", $domain, (int) $cursor, $this->page_size ) );
		$this->bailOnDbError();

		return is_array( $ids ) ? array_map( 'intval', $ids ) : [];
	}

	private function getRowsForIds( array $ids, $language, $domain, $modified_mo_only ) {
		$query = "
			SELECT
				s.id,
				st.status,
				s.domain_name_context_md5 AS ctx ,
				st.value AS translated,
				st.mo_string AS mo_string,
				s.value AS original,
				s.gettext_context,
				s.name,
				s.context AS source_context
			FROM {$this->wpdb->prefix}icl_strings s
			LEFT JOIN {$this->wpdb->prefix}icl_string_translations AS st
				ON s.id = st.string_id
					AND st.language = %s
					AND s.language != %s
			WHERE s.context = %s
				AND s.id IN (" . wpml_prepare_in( $ids, '%d' ) . ')';

		if ( $modified_mo_only ) {
			$query .= $this->getModifiedMOOnlyWhere();
		}

		$query .= ' ORDER BY s.id';

		$rows = $this->wpdb->get_results( $this->wpdb->prepare( $query, $language, $language, $domain ), ARRAY_A );
		$this->bailOnDbError();

		return is_array( $rows ) ? $rows : [];
	}

	private function getModifiedMOOnlyWhere() {
		return ' AND st.status IN (' .
			   wpml_prepare_in( [ ICL_TM_COMPLETE, ICL_TM_NEEDS_UPDATE ], '%d' ) .
			   ') AND st.value IS NOT NULL';
	}

	private function bailOnDbError() {
		if ( isset( $this->wpdb->last_error ) && $this->wpdb->last_error ) {
			throw new \RuntimeException( 'icl_strings retrieval failed: ' . $this->wpdb->last_error );
		}
	}
}
