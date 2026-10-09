<?php

namespace WPML\StringTranslation\Infrastructure\StringGettext\Repository;

use WPML\StringTranslation\Application\StringGettext\Repository\LoadedTextdomainRepositoryInterface;

class LoadedTextdomainRepository implements LoadedTextdomainRepositoryInterface {

	private $pluginDomains = [];

	private $themeDomains = [];

	public function __construct() {
		if ( ! function_exists( 'wp_get_theme' ) ) {
			return;
		}

		$theme = wp_get_theme();
		$domain = $theme->get( 'TextDomain' );

		if ( is_string( $domain ) && strlen( $domain ) > 0 ) {
			$this->addThemeDomain( $domain );
		}
	}

	public function addPluginDomain( string $domain ) {
		$this->pluginDomains[] = $domain;
	}

	public function getPluginDomains(): array {
		return $this->pluginDomains;
	}

	public function addThemeDomain( string $domain ) {
		$this->themeDomains[] = $domain;
	}

	public function getThemeDomains(): array {
		return $this->themeDomains;
	}
}