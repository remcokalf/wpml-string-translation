<?php

use WPML\ST\TranslationFile\StringCollation;

class WPML_ST_Strings_Stats {
	use StringCollation;

	private $sitepress;

	private $wpdb;

	private $stats;

	public function __construct( wpdb $wpdb, SitePress $sitepress ) {
		$this->wpdb      = $wpdb;
		$this->sitepress = $sitepress;
	}

	public function update( $component_name, $type, $domain ) {
		$count           = $this->get_count( $domain );
		$string_settings = $this->sitepress->get_setting( 'st' );
		$string_settings[ $type . '_localization_domains' ][ $component_name ][ $domain ] = $count;
		$this->sitepress->set_setting( 'st', $string_settings, true );
		$this->sitepress->save_settings();
	}

	private function get_count( $domain ) {
		if ( ! $this->stats ) {
			$this->set_stats();
		}

		return isset( $this->stats[ $domain ] ) ? (int) $this->stats[ $domain ]->count : 0;
	}

	private function set_stats() {
		$wpdb        = $this->wpdb;
		$this->stats = $wpdb->get_results(
			sprintf(
				"SELECT context, COUNT(id) count FROM {$wpdb->prefix}icl_strings GROUP BY context %s",
				esc_sql( $this->getCollateForContextColumn( $wpdb ) )
			),
			OBJECT_K
		);
	}
}
