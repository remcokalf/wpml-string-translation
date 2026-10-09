<?php

class WPML_Slug_Translation_Records_Factory {

	public function create( $type ) {
		global $wpdb;

		$cache_factory = new WPML_WP_Cache_Factory();

		if ( WPML_Slug_Translation_Factory::POST === $type ) {
			return new WPML_Post_Slug_Translation_Records( $wpdb, $cache_factory );
		} elseif ( WPML_Slug_Translation_Factory::TAX === $type ) {
			return new WPML_Tax_Slug_Translation_Records( $wpdb, $cache_factory );
		}

		return null;
	}

	public function createTaxRecords() {
		global $wpdb;
		$cache_factory = new WPML_WP_Cache_Factory();

		return new WPML_Tax_Slug_Translation_Records( $wpdb, $cache_factory );
	}
}
