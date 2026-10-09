<?php

namespace WPML\StringTranslation\Application\StringGettext\Repository;

interface LoadedTextdomainRepositoryInterface {
	public function addThemeDomain( string $domain );
	public function getThemeDomains(): array;
	public function addPluginDomain( string $domain );
	public function getPluginDomains(): array;
}