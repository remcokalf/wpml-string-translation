<?php

class WPML_ST_Slug_Custom_Type_Factory {

	private $sitepress;

	private $slug_records;

	private $slug_translations;


	public function __construct(
		SitePress $sitepress,
		WPML_Slug_Translation_Records $slug_records,
		WPML_ST_Slug_Translations $slug_translations
	) {
		$this->sitepress         = $sitepress;
		$this->slug_records      = $slug_records;
		$this->slug_translations = $slug_translations;
	}


	public function create( $name, $display_as_translated ) {
		$slug = $this->slug_records->get_slug( $name );

		return new WPML_ST_Slug_Custom_Type(
			$name,
			$display_as_translated,
			$slug->get_original_value(),
			$this->slug_translations->get( $slug, $display_as_translated )
		);
	}


}
