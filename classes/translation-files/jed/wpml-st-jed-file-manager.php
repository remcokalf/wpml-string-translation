<?php

use WPML\Collect\Support\Collection;
use WPML\ST\TranslationFile\Manager;

class WPML_ST_JED_File_Manager extends Manager {

	protected function getFileExtension() {
		return 'json';
	}

	public function isPartialFile() {
		return false;
	}

	protected function getDomains() {
		return $this->domains->getJEDDomains();
	}

	public function handles( $domain ) {
		return $this->getDomains()->contains( $domain )
			|| $this->domains->hasNoNativeTranslationFile( $domain );
	}
}
