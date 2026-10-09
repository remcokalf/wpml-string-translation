<?php

class WPML_Package_Translation_Schema {

	const OPTION_NAME      = 'wpml-package-translation-db-updates-run';
	const REQUIRED_VERSION = '0.0.4';

	const CLAMPED_POST_ID = 2147483647;

	static function run_update() {
		$updates_run = get_option( self::OPTION_NAME, array() );

		if ( defined( 'WPML_PT_VERSION_DEV' ) ) {
			delete_option( 'wpml-package-translation-string-packages-table-updated' );
			if ( ( $key = array_search( WPML_PT_VERSION_DEV, $updates_run ) ) !== false ) {
				unset( $updates_run[ $key ] );
			}
		}

		if ( ! in_array( self::REQUIRED_VERSION, $updates_run ) ) {
			self::build_icl_string_packages_table();
			self::build_icl_string_packages_columns_if_required();
			self::fix_icl_string_packages_ID_column();
			self::fix_icl_string_packages_post_id_column();
			self::widen_icl_string_packages_title_column();
			self::delete_clamped_string_packages();
			self::build_icl_strings_columns_if_required();

			$updates_run[] = self::REQUIRED_VERSION;

			update_option( self::OPTION_NAME, $updates_run );
		}

	}

	private static function current_table_has_column( $table_name, $column ) {
		global $wpdb;

		$cols  = $wpdb->get_results( 'SHOW COLUMNS FROM `' . (string) esc_sql( $table_name ) . '`' );
		$found = false;
		foreach ( $cols as $col ) {
			if ( $col->Field == $column ) {
				$found = true;
				break;
			}
		}

		return $found;
	}

	private static function add_string_package_id_to_icl_strings() {
		global $wpdb;
		$result = $wpdb->query( "ALTER TABLE `{$wpdb->prefix}icl_strings`
						ADD `string_package_id` BIGINT unsigned NULL AFTER value,
						ADD INDEX (`string_package_id`)" );

		do_action( 'wpml_st_strings_table_altered' );

		return $result;
	}

	private static function add_type_to_icl_strings() {
		global $wpdb;
		$result = $wpdb->query( "ALTER TABLE `{$wpdb->prefix}icl_strings` ADD `type` VARCHAR(40) NOT NULL DEFAULT 'LINE' AFTER string_package_id" );

		do_action( 'wpml_st_strings_table_altered' );

		return $result;
	}

	private static function add_title_to_icl_strings() {
		global $wpdb;
		$result = $wpdb->query( "ALTER TABLE `{$wpdb->prefix}icl_strings` ADD `title` TEXT NULL AFTER type" );

		do_action( 'wpml_st_strings_table_altered' );

		return $result;
	}

	public static function build_icl_strings_columns_if_required() {
		global $wpdb;

		if ( ! get_option( 'wpml-package-translation-string-table-updated' ) ) {
			$table_name = $wpdb->prefix . 'icl_strings';

			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name ) {
				if ( ! self::current_table_has_column( $table_name, 'string_package_id' ) ) {
					self::add_string_package_id_to_icl_strings();
				}

				if ( ! self::current_table_has_column( $table_name, 'type' ) ) {
					self::add_type_to_icl_strings();
				}

				if ( ! self::current_table_has_column( $table_name, 'title' ) ) {
					self::add_title_to_icl_strings();
				}
				update_option( 'wpml-package-translation-string-table-updated', true, 'no' );
			}
		}
	}

	private static function build_icl_string_packages_columns_if_required() {
		global $wpdb;

		if ( get_option( 'wpml-package-translation-string-packages-table-updated' ) != '0.0.2' ) {
			$table_name = $wpdb->prefix . 'icl_string_packages';

			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name ) {
				if ( ! self::current_table_has_column( $table_name, 'kind_slug' ) ) {
					self::add_kind_slug_to_icl_string_packages();
				}
				self::update_kind_slug();

				if ( ! self::current_table_has_column( $table_name, 'view_link' ) ) {
					self::add_view_link_to_icl_string_packages();
				}
				update_option( 'wpml-package-translation-string-packages-table-updated', '0.0.2', 'no' );
			}
		}
	}

	private static function add_kind_slug_to_icl_string_packages() {
		global $wpdb;
		$result = $wpdb->query( "ALTER TABLE `{$wpdb->prefix}icl_string_packages` ADD `kind_slug` varchar(160) DEFAULT '' NOT NULL AFTER `ID`" );

		return $result;
	}

	private static function add_view_link_to_icl_string_packages() {
		global $wpdb;

		return $wpdb->query( "ALTER TABLE `{$wpdb->prefix}icl_string_packages` ADD `view_link` TEXT NOT NULL AFTER `edit_link`" );
	}

	private static function fix_icl_string_packages_ID_column() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'icl_string_packages';
		if ( self::current_table_has_column( $table_name, 'id' ) ) {
			$wpdb->query( "ALTER TABLE `{$wpdb->prefix}icl_string_packages` CHANGE id ID BIGINT UNSIGNED NOT NULL auto_increment;" );
		}
	}

	private static function fix_icl_string_packages_post_id_column() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'icl_string_packages';
		if ( ! self::current_table_has_column( $table_name, 'post_id' ) ) {
			return;
		}

		$column = $wpdb->get_row( "SHOW COLUMNS FROM `{$wpdb->prefix}icl_string_packages` LIKE 'post_id'", ARRAY_A );
		if ( $column && 'bigint' === strtolower( substr( (string) $column['Type'], 0, 6 ) ) ) {
			return;
		}

		$wpdb->query( "ALTER TABLE `{$wpdb->prefix}icl_string_packages` MODIFY COLUMN `post_id` BIGINT UNSIGNED DEFAULT NULL;" );
	}

	private static function widen_icl_string_packages_title_column() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'icl_string_packages';
		if ( ! self::current_table_has_column( $table_name, 'title' ) ) {
			return;
		}

		$column = $wpdb->get_row( "SHOW COLUMNS FROM `{$wpdb->prefix}icl_string_packages` LIKE 'title'", ARRAY_A );
		if ( $column && 'text' === strtolower( (string) $column['Type'] ) ) {
			return;
		}

		$wpdb->query( "ALTER TABLE `{$wpdb->prefix}icl_string_packages` MODIFY COLUMN `title` TEXT NOT NULL;" );
	}

	private static function delete_clamped_string_packages() {
		global $wpdb;
		$table = $wpdb->prefix . 'icl_string_packages';

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return;
		}

		$wpdb->query(
			$wpdb->prepare( "DELETE FROM `{$wpdb->prefix}icl_string_packages` WHERE post_id = %d", self::CLAMPED_POST_ID )
		);
	}

	private static function build_icl_string_packages_table() {
		global $wpdb;

		$charset_collate = SitePress_Setup::get_charset_collate();

		if ( $wpdb->query(
			"CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}icl_string_packages` (
	                  `ID` bigint(20) unsigned NOT NULL auto_increment,
                  `kind_slug` varchar(160) NOT NULL,
                  `kind` varchar(160) NOT NULL,
                  `name` varchar(160) NOT NULL,
                  `title` TEXT NOT NULL,
                  `edit_link` TEXT NOT NULL,
                  `view_link` TEXT NOT NULL,
                  `post_id` BIGINT UNSIGNED DEFAULT NULL,
                  `word_count` VARCHAR(2000) DEFAULT NULL,
	                  `translator_note` LONGTEXT DEFAULT NULL,
	                  PRIMARY KEY  (`ID`)
	                ) " . esc_sql( $charset_collate )
		) === false ) {
			throw new Exception( $wpdb->last_error );
		}
	}

	private static function update_kind_slug() {
		global $wpdb;
		$kinds  = $wpdb->get_col( "SELECT kind FROM {$wpdb->prefix}icl_string_packages WHERE IFNULL(kind_slug,'')='' GROUP BY kind" );
		$result = ( count( $kinds ) == 0 );
		foreach ( $kinds as $kind ) {
			$kind_slug = sanitize_title_with_dashes( $kind );
			$result    = $wpdb->update( $wpdb->prefix . 'icl_string_packages', array( 'kind_slug' => $kind_slug ), array( 'kind' => $kind ) );
			if ( ! $result ) {
				break;
			}
		}

		return $result;
	}

}
