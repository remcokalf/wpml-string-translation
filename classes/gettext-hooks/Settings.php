<?php

namespace WPML\ST\Gettext;

use SitePress;

class Settings {

	private $sitepress;

	private $auto_register_settings;

	public function __construct(
		SitePress $sitepress,
		AutoRegisterSettings $auto_register_settings
	) {
		$this->sitepress              = $sitepress;
		$this->auto_register_settings = $auto_register_settings;
	}

	public function isTrackStringsEnabled() {
		return (bool) $this->getSTSetting( 'track_strings', false );
	}

	public function getTrackStringColor() {
		return (string) $this->getSTSetting( 'hl_color', '#e83200' );
	}

	public function isAutoRegistrationEnabled() {
		return (bool) $this->auto_register_settings->isEnabled();
	}

	public function isDomainRegistrationExcluded( $domain ) {
		if ( is_array( $domain ) && array_key_exists( 'domain', $domain ) ) {
			$domain = $domain[ 'domain' ];
		}
		return (bool) $this->auto_register_settings->isExcludedDomain( $domain );
	}

	private function getSTSetting( $key, $default = null ) {
		$settings = $this->sitepress->get_setting( 'st' );
		return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
	}
}
