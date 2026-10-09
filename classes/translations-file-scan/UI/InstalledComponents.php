<?php

namespace WPML\ST\MO\Scan\UI;

use WPML\Collect\Support\Collection;
use WPML_ST_Translations_File_Entry;

class InstalledComponents {

	public static function filter( Collection $components ) {
		return $components
			->reject( self::isPluginMissing() )
			->reject( self::isThemeMissing() );
	}

	public static function isPluginMissing() {
		return function( WPML_ST_Translations_File_Entry $entry ) {
			return 'plugin' === $entry->get_component_type()
			       && false === \WPML_ST_Path_Confinement::resolve_registered_plugin_file( $entry->get_component_id() );
		};
	}

	public static function isThemeMissing() {
		return function( WPML_ST_Translations_File_Entry $entry ) {
			return 'theme' === $entry->get_component_type()
			       && ! wp_get_theme( $entry->get_component_id() )->exists();
		};
	}
}
