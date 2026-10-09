<?php

namespace WP_CLI;

use WP_CLI;
use WP_Upgrader_Skin;

class UpgraderSkin extends WP_Upgrader_Skin {

	public $api;

	public function header() {}
	public function footer() {}
	public function bulk_header() {}
	public function bulk_footer() {}

	public function error( $error ) {
		if ( ! $error ) {
			return;
		}

		if ( is_string( $error ) && isset( $this->upgrader->strings[ $error ] ) ) {
			$error = $this->upgrader->strings[ $error ];
		}

		WP_CLI::warning( $error );
	}

	public function feedback( $string, ...$args ) {
		$args_array = [];
		foreach ( $args as $arg ) {
			$args_array[] = $args;
		}

		$this->process_feedback( $string, $args );
	}

	public function process_feedback( $string, $args ) {

		if ( 'parent_theme_prepare_install' === $string ) {
			WP_CLI::get_http_cache_manager()->whitelist_package( $this->api->download_link, 'theme', $this->api->slug, $this->api->version );
		}

		if ( isset( $this->upgrader->strings[ $string ] ) ) {
			$string = $this->upgrader->strings[ $string ];
		}

		if ( ! empty( $args ) && strpos( $string, '%' ) !== false ) {
			$string = vsprintf( $string, $args );
		}

		if ( empty( $string ) ) {
			return;
		}

		$string = str_replace( '&#8230;', '...', Utils\strip_tags( $string ) );
		$string = html_entity_decode( $string, ENT_QUOTES, get_bloginfo( 'charset' ) );

		WP_CLI::log( $string );
	}
}
