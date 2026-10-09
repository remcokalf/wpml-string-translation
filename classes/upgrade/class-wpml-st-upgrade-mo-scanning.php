<?php

class WPML_ST_Upgrade_MO_Scanning implements IWPML_St_Upgrade_Command {
	private $wpdb;

	public function __construct( wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	public function run() {
		return $this->create_table() && $this->add_mo_value_field_if_does_not_exist();
	}

	private function create_table() {
		$wpdb = $this->wpdb;

		$wpdb->query( "DROP TABLE IF EXISTS `{$wpdb->prefix}icl_mo_files_domains`" );

		return false !== $wpdb->query(
			$wpdb->prepare(
				"
					CREATE TABLE `{$wpdb->prefix}icl_mo_files_domains` (
				  `id` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
				  `file_path` varchar(250) NOT NULL,
				  `file_path_md5` varchar(32) NOT NULL,
				  `domain` varchar(160) NOT NULL,
				  `status` varchar(20) NOT NULL DEFAULT %s,
				  `num_of_strings` int(11) NOT NULL DEFAULT '0',
				  `last_modified` int(11) NOT NULL,
				  `component_type` enum('plugin','theme','other') NOT NULL DEFAULT 'other',
				  `component_id` varchar(100) DEFAULT NULL,
				  UNIQUE KEY `file_path_md5_UNIQUE` (`file_path_md5`)
					) " . esc_sql( $this->get_charset_collate() ),
				WPML_ST_Translations_File_Entry::NOT_IMPORTED
			)
		);
	}

	private function add_mo_value_field_if_does_not_exist() {
		$result = true;
		$wpdb   = $this->wpdb;

		$results = $this->wpdb->get_results( "SHOW COLUMNS FROM `{$wpdb->prefix}icl_string_translations` LIKE 'mo_string'" );
		if ( 0 === count( $results ) ) {

			$result = false !== $this->wpdb->query( "
				ALTER TABLE {$wpdb->prefix}icl_string_translations
				ADD COLUMN `mo_string` TEXT NULL DEFAULT NULL AFTER `value`;
			" );
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
		return __CLASS__ . '_4' ;
	}

	private function get_charset_collate() {
		$charset_collate = '';
		if ( method_exists( $this->wpdb, 'has_cap' ) && $this->wpdb->has_cap( 'collation' ) ) {
			$charset_collate = $this->wpdb->get_charset_collate();
		}

		return $charset_collate;
	}
}
