<?php

class WPML_ST_Translations_File_Components_Find_Plugin implements WPML_ST_Translations_File_Components_Find {
	private $debug_backtrace;

	private $plugin_dirs = array();

	private $plugin_ids;

	private $languages_plugin_dir;

	public function __construct( WPML_Debug_BackTrace $debug_backtrace ) {
		$this->debug_backtrace = $debug_backtrace;
		foreach ( array( WP_PLUGIN_DIR, WPML_PLUGINS_DIR ) as $plugin_dir ) {
			$plugin_dir = realpath( $plugin_dir );
			if ( false !== $plugin_dir ) {
				$this->plugin_dirs[] = $this->normalize_path( $plugin_dir );
			}
		}
		$this->plugin_dirs          = array_unique( $this->plugin_dirs );
		$this->languages_plugin_dir = $this->normalize_path( WP_LANG_DIR . '/plugins' );
	}

	public function find_id( $file ) {
		$plugin_id = WPML_ST_Path_Confinement::resolve_registered_plugin_id_for_path( $file );
		if ( false !== $plugin_id ) {
			return $plugin_id;
		}

		$directory = $this->find_plugin_directory( $file );
		if ( $directory ) {
			return $this->get_plugin_id_by_directory( $directory );
		}

		return $this->find_plugin_id_in_backtrace();
	}

	private function find_plugin_directory( $file ) {
		$file = $this->normalize_path( $file );
		foreach ( $this->plugin_dirs as $plugin_dir ) {
			if ( $this->is_path_in_directory( $file, $plugin_dir ) ) {
				return $this->extract_plugin_directory( $file, $plugin_dir );
			}
		}

		if ( $this->is_path_in_directory( $file, $this->languages_plugin_dir ) ) {
			return $this->extract_plugin_directory_from_languages_directory( $file );
		}

		return null;
	}

	private function find_plugin_id_in_backtrace() {
		$file = $this->find_file_in_backtrace();
		if ( ! $file ) {
			return null;
		}

		$plugin_id = WPML_ST_Path_Confinement::resolve_registered_plugin_id_for_path( $file );
		if ( false !== $plugin_id ) {
			return $plugin_id;
		}

		$directory = $this->find_plugin_directory( $file );

		return $directory ? $this->get_plugin_id_by_directory( $directory ) : null;
	}

	private function find_file_in_backtrace() {
		$stack = $this->debug_backtrace->get_backtrace();

		foreach ( $stack as $call ) {
			if ( isset( $call['function'] ) && 'load_plugin_textdomain' === $call['function'] ) {
				return $call['file'];
			}
		}

		return null;
	}

	private function extract_plugin_directory( $file_path, $plugin_dir ) {
		$dir = ltrim( substr( $file_path, strlen( $plugin_dir ) ), DIRECTORY_SEPARATOR );
		$dir = explode( DIRECTORY_SEPARATOR, $dir );

		return trim( $dir[0], DIRECTORY_SEPARATOR );
	}

	private function extract_plugin_directory_from_languages_directory( $file_path ) {
		$parts     = explode( DIRECTORY_SEPARATOR, $file_path );
		$file_name = current( explode( '.', end( $parts ) ) );

		if ( false !== strpos( $file_name, '_' ) ) {
			return substr( current( explode( '_', $file_name ) ), 0, -3 );
		}

		$parts = explode( '-', $file_name );
		array_pop( $parts );

		return implode( '-', $parts );
	}

	private function get_plugin_id_by_directory( $directory ) {
		foreach ( $this->get_plugin_ids() as $plugin_id ) {
			if ( 0 === strpos( $plugin_id, $directory . '/' ) ) {
				return $plugin_id;
			}
		}

		return null;
	}

	private function get_plugin_ids() {
		if ( null === $this->plugin_ids ) {
			$this->plugin_ids = array_keys( get_plugins() );
		}

		return $this->plugin_ids;
	}

	private function normalize_path( $path ) {
		return str_replace( array( '/', '\\' ), DIRECTORY_SEPARATOR, $path );
	}

	private function is_path_in_directory( $path, $directory ) {
		$directory = rtrim( $directory, DIRECTORY_SEPARATOR );

		return '' !== $directory
			&& ( $path === $directory || 0 === strpos( $path, $directory . DIRECTORY_SEPARATOR ) );
	}
}
