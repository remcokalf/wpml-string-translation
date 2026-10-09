<?php

namespace WPML\ST\TranslationFile;

class EntryQueries {

	public static function isType( $type ) {
		return function ( \WPML_ST_Translations_File_Entry $entry ) use ( $type ) {
			return $entry->get_component_type() === $type;
		};
	}

	public static function isExtension( $extension ) {
		return function ( \WPML_ST_Translations_File_Entry $file ) use ( $extension ) {
			return $file->get_extension() === $extension;
		};
	}

	public static function getResourceName() {
		return function ( \WPML_ST_Translations_File_Entry $entry ) {
			$function = 'get' . ucfirst( $entry->get_component_type() ) . 'Name';
			return self::$function( $entry );
		};
	}

	public static function getDomain() {
		return function( \WPML_ST_Translations_File_Entry $file ) {
			return $file->get_domain();
		};
	}
	private static function getPluginName( \WPML_ST_Translations_File_Entry $entry ) {
		$plugin_file = \WPML_ST_Path_Confinement::resolve_registered_plugin_file( $entry->get_component_id() );
		if ( false === $plugin_file ) {
			return '';
		}

		$data = get_plugin_data( $plugin_file, false, false );

		return isset( $data['Name'] ) ? (string) $data['Name'] : '';
	}

	private static function getThemeName( \WPML_ST_Translations_File_Entry $entry ) {
		return $entry->get_component_id();
	}

	private static function getOtherName( \WPML_ST_Translations_File_Entry $entry ) {
		return 'WordPress';
	}

}
