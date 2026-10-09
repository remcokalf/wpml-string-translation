<?php

namespace WPML\ST\Upgrade\Command;

use WPML\StringTranslation\Application\Setting\Repository\SettingsRepositoryInterface;
use WPML\ST\MO\Hooks\PreloadThemeMoFile;

class UpgradeAutoregisteringStrings implements \IWPML_St_Upgrade_Command {

	private $wpdb;

	private $sitepress;

	public function __construct( \wpdb $wpdb, \SitePress $sitepress ) {
		$this->wpdb      = $wpdb;
		$this->sitepress = $sitepress;
	}

	public function run() {
		$startWpmlVersion  = get_option( \WPML_Installation::WPML_START_VERSION_KEY );
		$isNewInstallation = ICL_SITEPRESS_VERSION === $startWpmlVersion;
		if ( $isNewInstallation ) {
			$settings = $this->sitepress->get_setting( 'st' );
			if ( ! is_array( $settings ) ) {
				$settings = [];
			}
			$settings['autoregister_strings'] = SettingsRepositoryInterface::AUTOREGISTER_STRINGS_TYPE_ONLY_VIEWED_BY_ADMIN;
			$this->sitepress->set_setting( 'st', $settings );
			$this->sitepress->set_setting( PreloadThemeMoFile::SETTING_KEY, PreloadThemeMoFile::SETTING_ENABLED_FOR_LOAD_TEXT_DOMAIN );
			$this->sitepress->save_settings();
		}

		$wpdb              = $this->wpdb;
		$tableName         = $wpdb->prefix . 'icl_strings';
		$stringsTableExist = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tableName ) ) === $tableName;

		if ( ! $stringsTableExist ) {
			return false;
		}

		$stringTypeCreated    = $this->createColumn( 'string_type', 'TINYINT NOT NULL DEFAULT 0' );
		$componentIdCreated   = $this->createColumn( 'component_id', 'VARCHAR(500) DEFAULT NULL' );
		$componentTypeCreated = $this->createColumn( 'component_type', 'TINYINT NOT NULL DEFAULT 0' );

		return $stringTypeCreated && $componentIdCreated && $componentTypeCreated;
	}

	private function createColumn( $columnName, $createColumnSql, $tableName = 'icl_strings' ) {
		$allowedColumns = [
			'string_type'   => 'TINYINT NOT NULL DEFAULT 0',
			'component_id'  => 'VARCHAR(500) DEFAULT NULL',
			'component_type' => 'TINYINT NOT NULL DEFAULT 0',
		];
		if ( 'icl_strings' !== $tableName || ! isset( $allowedColumns[ $columnName ] ) || $allowedColumns[ $columnName ] !== $createColumnSql ) {
			return false;
		}

		$wpdb         = $this->wpdb;
		$columnExists = $wpdb->get_var(
			$wpdb->prepare( "SHOW COLUMNS FROM `{$wpdb->prefix}icl_strings` LIKE %s", $columnName )
		) === $columnName;

		if ( ! $columnExists ) {
			switch ( $columnName ) {
				case 'string_type':
					return (bool) $wpdb->query( "ALTER TABLE {$wpdb->prefix}icl_strings ADD COLUMN `string_type` TINYINT NOT NULL DEFAULT 0" );
				case 'component_id':
					return (bool) $wpdb->query( "ALTER TABLE {$wpdb->prefix}icl_strings ADD COLUMN `component_id` VARCHAR(500) DEFAULT NULL" );
				case 'component_type':
					return (bool) $wpdb->query( "ALTER TABLE {$wpdb->prefix}icl_strings ADD COLUMN `component_type` TINYINT NOT NULL DEFAULT 0" );
			}
		} else {
			return true;
		}
	}

	public function run_ajax() {
		return $this->run();
	}

	public function run_frontend() {
	}

	public static function get_command_id() {
		return __CLASS__;
	}
}
