<?php

class WPML_ST_Upgrade_DB_String_Packages implements IWPML_St_Upgrade_Command {
	private $wpdb;

	public function __construct( wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	public function run() {
		$wpdb      = $this->wpdb;
		$table_name = $wpdb->prefix . 'icl_string_packages';

		$st_packages_table_exist = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name;

		if ( ! $st_packages_table_exist ) {
			return false;
		}

		$post_id_column_exists = $st_packages_table_exist
			? $wpdb->get_var( "SHOW COLUMNS FROM {$wpdb->prefix}icl_string_packages LIKE 'post_id'" ) === 'post_id'
			: false;

		if ( ! $post_id_column_exists ) {
			return (bool) $wpdb->query( "ALTER TABLE {$wpdb->prefix}icl_string_packages ADD COLUMN `post_id` BIGINT UNSIGNED DEFAULT NULL" );
		}

		return true;
	}

	public function run_ajax() {
		return $this->run();
	}

	public function run_frontend() {
	}

	public static function get_command_id() {
		return __CLASS__ . '_2.4.2';
	}
}
