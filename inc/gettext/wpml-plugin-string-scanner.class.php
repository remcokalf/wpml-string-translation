<?php

use WPML\API\Sanitize;

class WPML_Plugin_String_Scanner extends WPML_String_Scanner implements IWPML_ST_String_Scanner {

	private $current_plugin_file;

	private $current_plugin_id;

	public function scan() {
		$plugin_post = array_key_exists( 'plugin', $_POST ) ? $_POST['plugin'] : '';
		$plugin_file = (string) Sanitize::string( $plugin_post );
		$plugin_path = $this->resolve_plugin_path( $plugin_file );

		if ( false === $plugin_path ) {
			$this->scan_response( array() );
			return;
		}

		$this->current_plugin_file = $plugin_path;
		$this->current_plugin_id   = $plugin_file;
		$this->current_type        = 'plugin';
		$this->current_path        = dirname( $this->current_plugin_file );
		$this->text_domain         = $this->get_plugin_text_domain();
		$this->scan_starting( $this->current_type );
		$text_domain = $this->get_plugin_text_domain();
		$this->init_text_domain( $text_domain );
		$this->scan_plugin_files();
		$this->current_type = 'plugin';
		$stats = $this->set_stats( 'plugin_localization_domains', $plugin_file );
		$this->scan_response( $stats );
	}

	private function resolve_plugin_path( $plugin_file ) {
		return WPML_ST_Path_Confinement::resolve_registered_plugin_file( $plugin_file );
	}

	private function scan_plugin_files( $dir_or_file = false, $recursion = 0 ) {
		require_once WPML_ST_PATH . '/inc/potx.php';

		foreach ( $_POST['files'] as $file ) {
			$file = $this->confine_scanned_file( $file );

			if ( false === $file ) {
				continue;
			}

			if ( $this->is_js_file( $file ) ) {
				$jsScanner = new \WPML\ST\StringsScanning\JS\Scanner();
				$jsScanner->scan( $file, $this->text_domain, [ $this, 'store_results' ] );
				$this->add_scanned_file( $file );
			} else {
				_potx_process_file( $file, 0, array( $this, 'store_results' ), '_potx_save_version', $this->get_default_domain() );
				$this->add_scanned_file( $file );
			}
		}
	}

	private function confine_scanned_file( $file ) {
		$file = (string) Sanitize::string( $file );

		if ( $this->is_single_file_plugin() ) {
			$confined = realpath( $file );

			if ( false === $confined || $confined !== $this->current_plugin_file ) {
				return false;
			}
		} else {
			$confined = WPML_ST_Path_Confinement::resolve_contained( $file, $this->current_path );

			if ( false === $confined ) {
				return false;
			}
		}

		if ( ! is_file( $confined ) || ! is_readable( $confined ) ) {
			return false;
		}

		return $confined;
	}

	private function is_single_file_plugin() {
		return '.' === dirname( $this->current_plugin_id );
	}

	private function get_plugin_text_domain() {
		$text_domain = '';
		if ( ! function_exists( 'get_plugin_data' ) ) {
			include_once ABSPATH . '/wp-admin/includes/plugin.php';
		}
		if ( function_exists( 'get_plugin_data' ) ) {
			$plugin_data = get_plugin_data( $this->current_plugin_file );
			if ( isset( $plugin_data['TextDomain'] ) && $plugin_data['TextDomain'] != '' ) {
				$text_domain = $plugin_data['TextDomain'];

				return $text_domain;
			}

			return $text_domain;
		}

		return $text_domain;
	}
}
