<?php

class WPML_File_Name_Converter {
	private $home_path;

	public function transform_realpath_to_reference( $file ) {
		$home_path = $this->get_home_path();

		return str_replace( $home_path, '', $file );
	}

	public function transform_reference_to_realpath( $file ) {
		$home_path = $this->get_home_path();

		return trailingslashit( $home_path ) . ltrim( $file, '/\\' );
	}


	private function get_home_path() {
		if ( null === $this->home_path ) {
			if ( ! function_exists( 'get_home_path' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}

			$this->home_path = get_home_path();
		}

		return $this->home_path;
	}
}
