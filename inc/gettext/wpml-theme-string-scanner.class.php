<?php

class WPML_Theme_String_Scanner extends WPML_String_Scanner implements IWPML_ST_String_Scanner {

	public function scan() {
		$this->current_type = 'theme';
		$this->scan_starting( $this->current_type );
		$theme_info         = wp_get_theme();
		$text_domain        = $theme_info->get( 'TextDomain' );
		$current_theme_name = array_key_exists( 'theme', $_POST ) ? $_POST['theme'] : '';
		$current_theme      = wp_get_theme( $current_theme_name );
		$this->current_path = $this->resolve_theme_path( $current_theme_name, $current_theme );
		$this->text_domain  = $current_theme->get( 'TextDomain' );
		$this->init_text_domain( $text_domain );
		$this->scan_theme_files();
		$stats = $this->set_stats( 'theme_localization_domains', $current_theme_name );

		$this->scan_response( $stats );
	}

	private function resolve_theme_path( $theme_name, $theme ) {
		if ( ! $this->is_valid_theme_identifier( $theme_name ) ) {
			return '';
		}

		if ( ! $theme || ! $theme->exists() ) {
			return '';
		}

		$path = WPML_ST_Path_Confinement::resolve_contained(
			$theme->get_stylesheet_directory(),
			$theme->get_theme_root()
		);

		return false !== $path ? $path : '';
	}

	private function is_valid_theme_identifier( $theme_name ) {
		return is_string( $theme_name )
			&& '' !== $theme_name
			&& false === strpos( $theme_name, '/' )
			&& false === strpos( $theme_name, '\\' );
	}

	private function scan_theme_files() {
		require_once WPML_ST_PATH . '/inc/potx.php';

		if ( array_key_exists( 'files', $_POST ) ) {

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
					/* translators: Line in the progress log on the Theme and plugins localization page. %s: the path of the file being scanned. */
					$this->add_stat( sprintf( __( 'Scanning file: %s', 'wpml-string-translation' ), $file ) );
					_potx_process_file( $file, 0, array( $this, 'store_results' ), '_potx_save_version', $this->get_default_domain() );
					$this->add_scanned_file( $file );
				}
			}
		}
	}

	private function confine_scanned_file( $file ) {
		$file = (string) \WPML\API\Sanitize::string( $file );

		if ( ! $this->current_path ) {
			return false;
		}

		$confined = WPML_ST_Path_Confinement::resolve_contained( $file, $this->current_path );

		if ( false === $confined || ! is_file( $confined ) || ! is_readable( $confined ) ) {
			return false;
		}

		return $confined;
	}
}
