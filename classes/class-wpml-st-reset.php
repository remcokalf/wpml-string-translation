<?php

class WPML_ST_Reset {
	private $wpdb;

	private $settings;

	private $blog_id = 0;

	public function __construct( $wpdb, ?WPML_ST_Settings $settings = null ) {
		$this->wpdb = $wpdb;

		if ( ! $settings ) {
			$settings = new WPML_ST_Settings();
		}
		$this->settings = $settings;
	}

	public function reset() {
		$this->settings->delete_settings();

		$this->blog_id = (int) $this->wpdb->blogid;

		add_action( 'shutdown', array( $this, 'remove_db_tables' ), PHP_INT_MAX - 1 );
	}

	public function remove_db_tables() {
		$blog_id = $this->blog_id ?: (int) $this->wpdb->blogid;

		$is_multisite_reset = $blog_id && function_exists( 'is_multisite' ) && is_multisite();
		if ( $is_multisite_reset ) {
			switch_to_blog( $blog_id );
		}

		$wpdb = $this->wpdb;
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}icl_string_pages" );
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}icl_string_urls" );

		if ( $is_multisite_reset ) {
			restore_current_blog();
		}
	}
}
