<?php

class WPML_ST_Translations_File_Scan_Db_Table_List {
	private $wpdb;

	public function __construct( wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	public function get_tables() {
		return array(
			$this->wpdb->prefix . 'icl_strings',
			$this->wpdb->prefix . 'icl_string_translations',
		);
	}
}
