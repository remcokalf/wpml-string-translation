<?php

use function WPML\Container\make;

class WPML_ST_Plugin_Localization_UI_Factory {

	public function create(
		$localization = null,
		$utils = null,
		$repo = null
	) {
		global $wpdb;

		$localization = ( $localization) ? $localization : new WPML_Localization( $wpdb );
		$utils        = ( $utils ) ? $utils : make( WPML_ST_Plugin_Localization_Utils::class );
		$repo         = ( $repo ) ? $repo : make( \WPML\ST\TranslationFile\FilesToScanRepository::class );

		return new WPML_ST_Plugin_Localization_UI( $localization, $utils, $repo );
	}
}
