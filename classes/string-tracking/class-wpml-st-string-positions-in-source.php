<?php

class WPML_ST_String_Positions_In_Source extends WPML_ST_String_Positions {

	const KIND = ICL_STRING_TRANSLATION_STRING_TRACKING_TYPE_SOURCE;
	const TEMPLATE = 'positions-in-source.twig';

	private $sitepress;

	private $filesystem;

	private $filename_converter;
	private $wp_api;

	public function __construct(
		SitePress $sitePress,
		WPML_ST_DB_Mappers_String_Positions $string_position_mapper,
		IWPML_Template_Service $template_service,
		WPML_WP_API $wp_api
	) {
		$this->sitepress = $sitePress;
		$this->wp_api    = $wp_api;
		parent::__construct( $string_position_mapper, $template_service );
	}

	protected function get_model( $string_id ) {
		$positions       = $this->get_positions( $string_id );
		$st_settings     = $this->sitepress->get_setting( 'st' );
		$highlight_color = '#FFFF00';

		if ( array_key_exists( 'hl_color', $st_settings ) ) {
			$highlight_color = $st_settings['hl_color'];
		}

		return array(
			'positions' => $positions,
			'no_results_label' => __( 'No records found', 'wpml-string-translation' ),
			'highlight_color' => $highlight_color,
		);
	}

	protected function get_template_name() {
		return self::TEMPLATE;
	}

	private function get_positions( $string_id ) {
		$positions = array();
		$paths     = $this->get_mapper()->get_positions_by_string_and_kind( $string_id, self::KIND );
		$domain    = $this->get_string_domain( $string_id );

		foreach ( $paths as $path ) {
			$position = explode( '::', $path );

			$path = isset( $position[0] ) ? $position[0] : null;

			if( ! $this->get_filesystem()->exists( $path ) ) {
				$path = $this->maybe_transform_from_relative_path_to_absolute_path( $path );
			}

			$path = $path
				? WPML_ST_Path_Confinement::resolve_source_path_for_domain( $path, $domain )
				: false;

			if ( $path && $this->get_filesystem()->is_readable( $path ) && $this->get_filesystem()->is_file( $path ) ) {
				$positions[] = array(
					'path' => $path,
					'line' => isset( $position[1] ) ? $position[1] : null,
					'content' => $this->get_filesystem()->get_contents_array( $path ),
				);
			}
		}

		return $positions;
	}

	private function get_string_domain( $string_id ) {
		global $wpdb;

		$strings_mapper = new WPML_ST_DB_Mappers_Strings( $wpdb );
		$string         = (array) $strings_mapper->getById( $string_id );

		return isset( $string['context'] ) ? (string) $string['context'] : '';
	}

	private function maybe_transform_from_relative_path_to_absolute_path( $path ) {
		$path = $this->get_filename_converter()->transform_reference_to_realpath( $path );

		if ( $this->get_filesystem()->exists( $path ) ) {
			return $path;
		}

		return false;
	}

	private function get_filesystem() {
		if ( ! $this->filesystem ) {
			$this->filesystem = $this->get_wp_api()->get_wp_filesystem();
		}

		return $this->filesystem;
	}

	private function get_wp_api() {
		if ( ! $this->wp_api ) {
			$this->wp_api = new WPML_WP_API();
		}

		return $this->wp_api;
	}

	private function get_filename_converter() {
		if ( ! $this->filename_converter ) {
			$this->filename_converter = new WPML_File_Name_Converter();
		}

		return $this->filename_converter;
	}
}
