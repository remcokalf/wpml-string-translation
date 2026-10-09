<?php

class WPML_ST_Translations_File_Scan_Db_Charset_Validation implements WPML_ST_Translations_File_Scan_Charset_Validation {
	private $wpdb;

	private $table_list;

	public function __construct( wpdb $wpdb, WPML_ST_Translations_File_Scan_Db_Table_List $table_list ) {
		$this->wpdb       = $wpdb;
		$this->table_list = $table_list;
	}


	public function is_valid() {
		if ( ! $this->wpdb->has_cap( 'utf8mb4' ) ) {
			return false;
		}

		$chunks = array();
		foreach ( $this->table_list->get_tables() as $table ) {
			$chunks[] = $this->get_unique_collation_list_from_table( $table );
		}

		$collations = call_user_func_array( 'array_merge', $chunks );
		$collations = array_unique( $collations );

		return count( $collations ) < 2;
	}



	private function get_unique_collation_list_from_table( $table ) {
		$wpdb = $this->wpdb;

		if ( $wpdb->prefix . 'icl_strings' === $table ) {
			$columns = $wpdb->get_results( "SHOW FULL COLUMNS FROM `{$wpdb->prefix}icl_strings` WHERE Collation LIKE 'utf8mb4%'" );
		} elseif ( $wpdb->prefix . 'icl_string_translations' === $table ) {
			$columns = $wpdb->get_results( "SHOW FULL COLUMNS FROM `{$wpdb->prefix}icl_string_translations` WHERE Collation LIKE 'utf8mb4%'" );
		} else {
			return array();
		}

		return array_unique( wp_list_pluck( $columns, 'Collation' ) );
	}
}
