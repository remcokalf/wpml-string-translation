<?php

class WPML_ST_Slug_Custom_Type {
	private $name;

	private $display_as_translated;

	private $slug;

	private $slug_translation;

	public function __construct( $name, $display_as_translated, $slug, $slug_translation ) {
		$this->name                  = $name ?: '';
		$this->display_as_translated = (bool) $display_as_translated;
		$this->slug                  = $slug ?: '';
		$this->slug_translation      = $slug_translation ?: '';
	}


	public function get_name() {
		return $this->name;
	}

	public function is_display_as_translated() {
		return $this->display_as_translated;
	}

	public function get_slug() {
		return $this->slug;
	}

	public function get_slug_translation() {
		return $this->slug_translation;
	}

	public function is_using_tags() {
		$pattern = '#%([^/]+)%#';

		$slug = isset($this->slug) && is_string($this->slug) ? $this->slug : '';
		$slug_translation = isset($this->slug_translation) && is_string($this->slug_translation) ? $this->slug_translation : '';

		return preg_match($pattern, $slug) || preg_match($pattern, $slug_translation);
	}
}
