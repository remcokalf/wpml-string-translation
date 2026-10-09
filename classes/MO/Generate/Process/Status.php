<?php

namespace WPML\ST\MO\Generate\Process;

class Status {
	private $sitepress;

	private $optionPrefix;

	public function __construct( \SitePress $sitepress, $optionPrefix = null ) {
		$this->sitepress    = $sitepress;
		$this->optionPrefix = $optionPrefix ?: self::class;
	}

	public function markComplete( $allSites = false ) {
		$settings                                      = $this->sitepress->get_setting( 'st', [] );
		$settings[ $this->getOptionName( $allSites ) ] = true;
		$this->sitepress->set_setting( 'st', $settings, true );
	}

	public function markIncomplete( $allSites = false ) {
		$settings = $this->sitepress->get_setting( 'st', [] );
		unset( $settings[ $this->getOptionName( $allSites ) ] );
		$this->sitepress->set_setting( 'st', $settings, true );
	}

	public function markIncompleteForAll() {
		$this->markIncomplete( true );
	}

	public function isComplete() {
		$st_settings = $this->sitepress->get_setting( 'st', [] );

		return isset( $st_settings[ $this->getOptionName( false ) ] );
	}

	public function isCompleteForAllSites() {
		$st_settings = $this->sitepress->get_setting( 'st', [] );

		return isset( $st_settings[ $this->getOptionName( true ) ] );
	}

	private function getOptionName( $allSites ) {
		return $allSites ? $this->optionPrefix . '_has_run_all_sites' : $this->optionPrefix . '_has_run';
	}
}
