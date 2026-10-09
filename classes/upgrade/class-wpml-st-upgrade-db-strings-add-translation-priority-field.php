<?php

class WPML_ST_Upgrade_DB_Strings_Add_Translation_Priority_Field implements IWPML_St_Upgrade_Command {
	private $wpdb;

	public function __construct( wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	public function run() {
		$result = null;
		$wpdb   = $this->wpdb;

		$table_name = $wpdb->prefix . 'icl_strings';
		$results = $wpdb->get_results( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );
		if ( 0 !== count( $results ) ) {
			$s_results = $wpdb->get_results( "SHOW FIELDS FROM {$wpdb->prefix}icl_strings WHERE FIELD = 'translation_priority'" );
			if ( 0 === count( $s_results ) ) {
				$result = false !== $wpdb->query(
					"ALTER TABLE {$wpdb->prefix}icl_strings
					ADD COLUMN `translation_priority` varchar(160) NOT NULL"
				);
			}

			if ( false !== $result ) {
				$results = $wpdb->get_results( "SHOW KEYS FROM {$wpdb->prefix}icl_strings WHERE Key_name='icl_strings_translation_priority'" );
				if ( 0 === count( $results ) ) {
					$result = false !== $wpdb->query(
						"ALTER TABLE {$wpdb->prefix}icl_strings
						ADD INDEX `icl_strings_translation_priority` ( `translation_priority` ASC )"
					);
				} else {
					$result = true;
				}
			}
		}

		return (bool) $result;
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
