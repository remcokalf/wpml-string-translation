<?php

namespace WPML\ST\MO\Hooks;

use WPML\Collect\Support\Collection;
use WPML\ST\Gettext\AutoRegisterSettings;
use function WPML\Container\make;

class PreloadThemeMoFile implements \IWPML_Action {

	const SETTING_KEY = 'theme_localization_load_textdomain';
	const SETTING_DISABLED = 0;
	const SETTING_ENABLED = 1;
	const SETTING_ENABLED_FOR_LOAD_TEXT_DOMAIN = 2;

	private $sitepress;

	private $wpdb;

	public function __construct( \SitePress $sitepress, \wpdb $wpdb ) {
		$this->sitepress = $sitepress;
		$this->wpdb      = $wpdb;
	}


	public function add_hooks() {
		$domainsSetting = $this->sitepress->get_setting( 'gettext_theme_domain_name' );
		$domains = empty( $domainsSetting ) ? [] : explode( ',', $domainsSetting );
		$domains = \wpml_collect( array_map( 'trim', $domains ) );

		$loadTextDomainSetting = (int) $this->sitepress->get_setting( static::SETTING_KEY );
		$isEnabled = $loadTextDomainSetting === static::SETTING_ENABLED;

		if ( $loadTextDomainSetting === static::SETTING_ENABLED_FOR_LOAD_TEXT_DOMAIN ) {
			$autoStrings = make( AutoRegisterSettings::class );
			$isEnabled = $autoStrings->isEnabled();
		}

		if ( $isEnabled && $domains->count() ) {
			$this->getMOFilesByDomainsAndLocale( $domains, determine_locale() )->map( function ( $fileResult ) {
				load_textdomain( $fileResult->domain, $fileResult->file_path );
			} );
		}
	}

	private function getMOFilesByDomainsAndLocale( $domains, $locale ) {
		$wpdb         = $this->wpdb;
		$domainValues = $domains->toArray();
		$values       = array_merge( $domainValues, [ '((\\/|-)' . $locale . '(\\.|-))+' ] );

		return \wpml_collect(
			$wpdb->get_results(
				$wpdb->prepare(
					"SELECT file_path, domain
					FROM {$wpdb->prefix}icl_mo_files_domains
					WHERE domain IN (" . implode( ', ', array_fill( 0, count( $domainValues ), '%s' ) ) . ') AND file_path REGEXP %s',
					$values
				)
			)
		);
	}
}
