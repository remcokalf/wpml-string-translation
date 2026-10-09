<?php

class WPML_ST_String_Factory {
	private $wpdb;

	public function __construct( wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	private $string_id_cache = array();

	private $string_cache = array();

	public function find_by_id( $string_id ) {
		$this->string_cache[ $string_id ] = isset( $this->string_cache[ $string_id ] )
			? $this->string_cache[ $string_id ] : new WPML_ST_String( $string_id, $this->wpdb );

		return $this->string_cache[ $string_id ];
	}

	public function find_by_name( $name ) {
		$wpdb = $this->wpdb;

		$cache_key                           = md5( $wpdb->prefix . '|name:' . $name );
		$this->string_id_cache[ $cache_key ] = isset( $this->string_id_cache[ $cache_key ] )
			? $this->string_id_cache[ $cache_key ]
			: (int) $wpdb->get_var(
				$wpdb->prepare( "SELECT id FROM {$wpdb->prefix}icl_strings WHERE name=%s LIMIT 1", $name )
			);
		$string_id                           = $this->string_id_cache[ $cache_key ];
		$this->string_cache[ $string_id ]    = isset( $this->string_cache[ $string_id ] )
			? $this->string_cache[ $string_id ] : new WPML_ST_String( $string_id, $this->wpdb );

		return $this->string_cache[ $this->string_id_cache[ $cache_key ] ];
	}

	public function find_admin_by_name( $name ) {
		$wpdb      = $this->wpdb;
		$string_id = (int) $this->wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}icl_strings WHERE name=%s LIMIT 1", $name ) );
		return new WPML_ST_Admin_String( $string_id, $this->wpdb );
	}

	public function get_string_id( $string, $context, $name = false ) {
		list( $domain, $gettext_context ) = wpml_st_extract_context_parameters( $context );
		$wpdb = $this->wpdb;

		$cache_key = md5( (string) wp_json_encode( array( $wpdb->prefix, $string, $gettext_context, $domain, $name ) ) );
		$this->string_id_cache[ $cache_key ] = isset( $this->string_id_cache[ $cache_key ] )
			? $this->string_id_cache[ $cache_key ]
			: (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$wpdb->prefix}icl_strings
					WHERE BINARY value = %s
						AND ( %d = 0 OR gettext_context = %s )
						AND ( %d = 0 OR context = %s )
						AND ( %d = 0 OR name = %s )
					LIMIT 1",
					$string,
					$gettext_context ? 1 : 0,
					$gettext_context ?: '',
					$domain ? 1 : 0,
					$domain ?: '',
					false !== $name ? 1 : 0,
					false !== $name ? $name : ''
				)
			);

		return $this->string_id_cache[ $cache_key ];
	}

	public function clear_string_id_cache() {
		$this->string_id_cache = array();
	}
}
