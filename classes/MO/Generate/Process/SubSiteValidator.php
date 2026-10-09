<?php

namespace WPML\ST\MO\Generate\Process;

use function WPML\Container\make;

class SubSiteValidator {
	public function isValid() {
		global $sitepress;

		return $sitepress->is_setup_complete() && $this->hasTranslationFilesTable();
	}

	private function hasTranslationFilesTable() {
		return make( \WPML_Upgrade_Schema::class )->does_table_exist( 'icl_mo_files_domains' );
	}
}