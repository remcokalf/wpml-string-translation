<?php

class WPML_ST_DB_Mappers_String_Positions {
	private $wpdb;

	public function __construct( wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	public function get_count_of_positions_by_string_and_kind( $string_id, $kind ) {
		$wpdb = $this->wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(id)
				 FROM {$wpdb->prefix}icl_string_positions
				 WHERE string_id = %d AND kind = %d",
				$string_id,
				$kind
			)
		);
	}

	public function get_positions_by_string_and_kind( $string_id, $kind ) {
		$wpdb = $this->wpdb;

		return $wpdb->get_col(
			$wpdb->prepare(
				"SELECT position_in_page
				 FROM {$wpdb->prefix}icl_string_positions
				 WHERE string_id = %d AND kind = %d",
				$string_id,
				$kind
			)
		);
	}

	public function is_string_tracked( $string_id, $position, $kind ) {
		$wpdb = $this->wpdb;

		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id
				 FROM {$wpdb->prefix}icl_string_positions
				 WHERE string_id = %d AND position_in_page = %s AND kind = %s",
				$string_id,
				$position,
				$kind
			)
		);
	}

	public function insert( $string_id, $position, $kind ) {
		$this->wpdb->insert( $this->wpdb->prefix . 'icl_string_positions', array(
			'string_id'        => $string_id,
			'kind'             => $kind,
			'position_in_page' => $position,
		) );
	}
}
