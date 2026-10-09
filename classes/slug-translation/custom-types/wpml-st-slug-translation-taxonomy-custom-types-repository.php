<?php

class WPML_ST_Slug_Translation_Taxonomy_Custom_Types_Repository implements WPML_ST_Slug_Translation_Custom_Types_Repository {
	private $sitepress;

	private $custom_type_factory;

	private $settings_repository;

	private $settings;

	public function __construct(
		SitePress $sitepress,
		WPML_ST_Slug_Custom_Type_Factory $custom_type_factory,
		WPML_ST_Tax_Slug_Translation_Settings $settings_repository
	) {
		$this->sitepress           = $sitepress;
		$this->custom_type_factory = $custom_type_factory;
		$this->settings_repository = $settings_repository;
	}


	public function get() {
		return array_map(
			array( $this, 'build_object' ),
			array_values( array_filter(
				get_taxonomies( array( 'publicly_queryable' => true ) ),
				array( $this, 'filter' )
			) )
		);
	}

	private function filter( $type ) {
		$settings = $this->get_taxonomy_slug_translation_settings();

		return isset( $settings[ $type ] )
		       && $settings[ $type ]
		       && $this->sitepress->is_translated_taxonomy( $type );
	}

	private function build_object( $type ) {
		return $this->custom_type_factory->create( $type, $this->is_display_as_translated( $type ) );
	}

	private function get_taxonomy_slug_translation_settings() {
		if ( null === $this->settings ) {
			$this->settings = $this->settings_repository->get_types();
		}

		return $this->settings;
	}


	private function is_display_as_translated( $type ) {
		return $this->sitepress->is_display_as_translated_taxonomy( $type );
	}
}