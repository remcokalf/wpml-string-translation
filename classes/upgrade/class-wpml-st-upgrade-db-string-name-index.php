<?php

class WPML_ST_Upgrade_DB_String_Name_Index implements IWPML_St_Upgrade_Command {
	private $wpdb;

	public function __construct( wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	public function run() {
		$result = true;
		$wpdb   = $this->wpdb;

		$table_name = $wpdb->prefix . 'icl_strings';
		$results = $wpdb->get_results( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );
		if ( 0 !== count( $results ) ) {
			$results = $wpdb->get_results( "SHOW KEYS FROM {$wpdb->prefix}icl_strings WHERE Key_name='icl_strings_name'" );
			if ( 0 === count( $results ) ) {
				$result = false !== $wpdb->query(
					"ALTER TABLE {$wpdb->prefix}icl_strings
					ADD INDEX `icl_strings_name` (`name` ASC);"
				);
			}
		}

		return $result;
	}

	public function run_ajax() {
		$this->run();
	}

	public function run_frontend() {
		$this->run();
	}

	public static function get_command_id() {
		return __CLASS__ . '_2';
	}
}
