<?php

class WPML_ST_Post_Slug_Translation_Settings extends WPML_ST_Slug_Translation_Settings {

	const KEY_IN_SITEPRESS_SETTINGS = 'posts_slug_translation';

	private $sitepress;

	private $settings;

	public function __construct( SitePress $sitepress ) {
		$this->sitepress = $sitepress;
		$this->settings  = $sitepress->get_setting( self::KEY_IN_SITEPRESS_SETTINGS, array( ) );
	}

	public function set_enabled( $enabled ) {
		parent::set_enabled( $enabled );

		$this->settings['on'] = (int) $enabled;
	}

	public function is_translated( $type ) {
		return ! empty( $this->settings['types'][ $type ] );
	}

	public function set_type( $type, $is_enabled ) {
		if ( $is_enabled ) {
			$this->settings['types'][ $type ] = 1;
		} else {
			unset( $this->settings['types'][ $type ] );
		}
	}

	public function save() {
		$this->sitepress->set_setting( self::KEY_IN_SITEPRESS_SETTINGS, $this->settings, true );
	}
}
