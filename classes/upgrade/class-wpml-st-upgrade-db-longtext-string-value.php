<?php

class WPML_ST_Upgrade_DB_Longtext_String_Value implements IWPML_St_Upgrade_Command {
	private $wpdb;

	public function __construct( wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	public function run() {
		$result = true;
		$wpdb   = $this->wpdb;

		$table_name = $wpdb->prefix . 'icl_strings';
		if ( count( (array) $wpdb->get_results( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) ) ) {
			$result = false !== $wpdb->query(
				"ALTER TABLE {$wpdb->prefix}icl_strings
				MODIFY COLUMN `value` LONGTEXT NOT NULL;"
			);
		}

		$table_name = $wpdb->prefix . 'icl_string_translations';
		if ( count( (array) $wpdb->get_results( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) ) ) {
			$result = ( false !== $wpdb->query(
				"ALTER TABLE {$wpdb->prefix}icl_string_translations
				MODIFY COLUMN `value` LONGTEXT NULL DEFAULT NULL,
				MODIFY COLUMN `mo_string` LONGTEXT NULL DEFAULT NULL;"
			) ) && $result;
		}

		return $result;
	}

	public function run_ajax() {
		return $this->run();
	}

	public function run_frontend() {
		return $this->run();
	}

	public static function get_command_id() {
		return __CLASS__;
	}
}
