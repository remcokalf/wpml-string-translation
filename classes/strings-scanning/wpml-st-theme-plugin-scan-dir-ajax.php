<?php

use WPML\ST\StringsScanning\JS\ScriptRegistry;

class WPML_ST_Theme_Plugin_Scan_Dir_Ajax {

	private $scan_dir;

	public function __construct( WPML_ST_Scan_Dir $scan_dir ) {
		$this->scan_dir = $scan_dir;
	}

	public function add_hooks() {
		\WPML\Request\Adapter\Ajax::register( 'wpml_get_files_to_scan', \WPML\Request\Policy\Policy::capability( 'wpml_manage_theme_and_plugin_localization', \WPML\Request\Policy\Authenticity::actionNonce( 'wpml_get_files_to_scan', 'nonce' ) ), array( $this, 'get_files' ) );
	}

	public function get_files() {
		if ( ! current_user_can( 'wpml_manage_theme_and_plugin_localization' ) ) {
			/* translators: Error message shown when the user does not have the rights to do what they asked for. Past participle used as a state, lower case in the source. */
			wp_send_json_error( __( 'not allowed', 'wpml-string-translation' ) );
			return;
		}

		list( $type, $id, $folder ) = $this->get_component_data();
		$files_found_chunks         = [];
		$result                     = [];

		if ( $folder ) {
			$file_type = [ 'php', 'inc' ];

			$files_found_chunks[] = $this->scan_dir->scan(
				$folder,
				$file_type,
				$this->is_one_file_plugin( $type, $id ),
				$this->get_folders_to_ignore()
			);

			$files_found_chunks[] = ScriptRegistry::getAbsScriptPathsForComponents( $id, $type );

			$files = call_user_func_array( 'array_merge', $files_found_chunks );

			$result = array(
				'files'            => $files,
				'no_files_message' => __( 'Files already scanned.', 'wpml-string-translation' ),
			);
		}

		wp_send_json_success( $result );
	}

	private function get_component_data() {
		$type   = null;
		$id     = null;
		$root   = null;
		$folder = null;
		$theme_id     = $this->get_posted_component_id( 'theme' );
		$plugin_id    = $this->get_posted_component_id( 'plugin' );
		$mu_plugin_id = $this->get_posted_component_id( 'mu-plugin' );

		if ( null !== $theme_id ) {
			$type = 'theme';
			$id   = $theme_id;
			if ( $this->is_single_path_segment( $id ) ) {
				$theme = wp_get_theme( $id );
				if ( $theme->exists() ) {
					$root   = $theme->get_theme_root();
					$folder = $theme->get_stylesheet_directory();
				}
			}
		} elseif ( null !== $plugin_id ) {
			$type   = 'plugin';
			$id     = $plugin_id;
			$folder = WPML_ST_Path_Confinement::resolve_registered_plugin_root( $id );
		} elseif ( null !== $mu_plugin_id ) {
			$type   = 'mu-plugin';
			$id     = $mu_plugin_id;
			$root   = WPMU_PLUGIN_DIR;
			$folder = $this->is_single_path_segment( $id ) ? $root . '/' . $id : null;
		}

		if ( $folder && $root ) {
			$confined = WPML_ST_Path_Confinement::resolve_contained( $folder, $root );
			$folder   = false !== $confined ? $confined : null;
		}

		return [ $type, $id, $folder ];
	}

	private function get_posted_component_id( $key ) {
		if ( ! array_key_exists( $key, $_POST ) || ! is_string( $_POST[ $key ] ) ) {
			return null;
		}

		return sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
	}

	private function is_single_path_segment( $id ) {
		return '' !== $id && false === strpos( $id, '/' ) && false === strpos( $id, '\\' );
	}

	private function is_one_file_plugin( $type, $id ) {
		return in_array( $type, [ 'plugin', 'mu-plugin' ], true ) && '.' === dirname( $id );
	}

	private function get_folders_to_ignore() {
		$folders = [
			WPML_ST_Scan_Dir::PLACEHOLDERS_ROOT . '/node_modules',
			WPML_ST_Scan_Dir::PLACEHOLDERS_ROOT . '/tests',
			WPML_ST_Scan_Dir::PLACEHOLDERS_ROOT . '/*/[Tt]ests',
			WPML_ST_Scan_Dir::PLACEHOLDERS_ROOT . '/vendor-bin',
		];

		return $folders;
	}
}
