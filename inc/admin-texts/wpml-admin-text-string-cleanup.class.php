<?php

require_once dirname( __FILE__ ) . '/wpml-admin-text-config-sync.class.php';

class WPML_Admin_Text_String_Cleanup {

	const DOMAIN_PREFIX = 'admin_texts_';

	private $wpdb;

	private $config_sync;

	public function __construct( wpdb $wpdb, WPML_Admin_Text_Config_Sync $config_sync ) {
		$this->wpdb        = $wpdb;
		$this->config_sync = $config_sync;
	}

	public function cleanup( array $string_ids ) {
		$string_ids = array_values( array_filter( array_map( 'intval', $string_ids ) ) );

		if ( empty( $string_ids ) ) {
			return;
		}

		$prepared_ids = wpml_prepare_in( $string_ids, '%d' );
		$strings = $this->wpdb->get_results(
			"SELECT context, name
			 FROM {$this->wpdb->prefix}icl_strings
			 WHERE id IN ({$prepared_ids})"
		);
		$to_remove = array();

		foreach ( $strings as $string ) {
			$path = $this->get_option_path( $string->context, $string->name );

			if ( empty( $path ) || $this->config_sync->is_path_configured( $path ) ) {
				continue;
			}

			$to_remove = WPML_Admin_Text_Config_Sync::merge_names(
				$to_remove,
				$this->path_to_tree( $path )
			);
		}

		if ( empty( $to_remove ) ) {
			return;
		}

		$option_names = get_option( WPML_Admin_Text_Functionality::TRANSLATABLE_NAMES_SETTING, array() );
		$option_names = is_array( $option_names ) ? $option_names : array();
		$next         = WPML_Admin_Text_Config_Sync::subtract( $option_names, $to_remove );

		if ( $next !== $option_names ) {
			update_option( WPML_Admin_Text_Functionality::TRANSLATABLE_NAMES_SETTING, $next, 'no' );
		}
	}

	private function get_option_path( $context, $name ) {
		if ( 0 !== strpos( $context, self::DOMAIN_PREFIX ) ) {
			return array();
		}

		$option_name = substr( $context, strlen( self::DOMAIN_PREFIX ) );
		if ( '' === $option_name ) {
			return array();
		}

		if ( $option_name === $name || '[' . $option_name . ']' === $name ) {
			return array( $option_name );
		}

		$option_prefix = '[' . $option_name . ']';
		if ( 0 !== strpos( $name, $option_prefix ) ) {
			return array();
		}

		$path      = array( $option_name );
		$remainder = substr( $name, strlen( $option_prefix ) );
		preg_match_all( '/\[([^\]]+)\]|([^\[\]]+)/', $remainder, $matches, PREG_SET_ORDER );

		foreach ( $matches as $match ) {
			$key = isset( $match[1] ) && '' !== $match[1] ? $match[1] : $match[2];
			if ( '' !== $key ) {
				$path[] = $key;
			}
		}

		return count( $path ) > 1 ? $path : array();
	}

	private function path_to_tree( array $path ) {
		$tree = 1;

		foreach ( array_reverse( $path ) as $key ) {
			$tree = array( $key => $tree );
		}

		return $tree;
	}
}
