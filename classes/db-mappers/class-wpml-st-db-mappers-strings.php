<?php

class WPML_ST_DB_Mappers_Strings {
	private $wpdb;

	public function __construct( wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	public function get_all_by_context( $context ) {
		$wpdb = $this->wpdb;

		if ( false === strpos( $context, '%' ) ) {
			return $wpdb->get_results(
				$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}icl_strings WHERE context = %s", esc_sql( $context ) ),
				ARRAY_A
			);
		}

		return $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}icl_strings WHERE context LIKE %s", esc_sql( $context ) ),
			ARRAY_A
		);
	}

	public function getByDomainAndValue( $domain, $value ) {
		$wpdb = $this->wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}icl_strings WHERE `context` = %s and `value` = %s",
				$domain,
				$value
			)
		);
	}

	public function getById( $id ) {
		$wpdb = $this->wpdb;

		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}icl_strings WHERE id = %d", $id )
		);
	}
}
