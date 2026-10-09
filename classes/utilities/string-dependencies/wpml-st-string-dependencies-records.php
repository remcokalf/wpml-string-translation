<?php

class WPML_ST_String_Dependencies_Records {

	private $wpdb;

	public function __construct( wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	public function get_parent_id_from( $type, $id ) {
		$wpdb = $this->wpdb;

		switch ( $type ) {
			case 'package':
				return (int) $wpdb->get_var(
					$wpdb->prepare( "SELECT post_id FROM {$wpdb->prefix}icl_string_packages WHERE ID = %d", $id )
				);

			case 'string':
				return (int) $wpdb->get_var(
					$wpdb->prepare( "SELECT string_package_id FROM {$wpdb->prefix}icl_strings WHERE id = %d", $id )
				);

			default:
				return 0;
		}
	}

	public function get_child_ids_from( $type, $id ) {
		$wpdb = $this->wpdb;

		switch ( $type ) {
			case 'post':
				$ids = $wpdb->get_col(
					$wpdb->prepare( "SELECT id FROM {$wpdb->prefix}icl_string_packages WHERE post_id = %d", $id )
				);
				break;

			case 'package':
				$ids = $wpdb->get_col(
					$wpdb->prepare( "SELECT ID FROM {$wpdb->prefix}icl_strings WHERE string_package_id = %d", $id )
				);
				break;

			default:
				return array();
		}

		return array_map( 'intval', $ids );
	}
}
