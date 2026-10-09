<?php

use WPML\ST\TranslationFile\EntryQueries;

class WPML_ST_Translations_File_Dictionary {
	private $storage;

	public function __construct( WPML_ST_Translations_File_Dictionary_Storage $storage ) {
		$this->storage = $storage;
	}

	public function find_file_info_by_path( $file_path ) {
		$result = $this->storage->find( $file_path );
		if ( $result ) {
			return current( $result );
		}

		return null;
	}

	public function save( WPML_ST_Translations_File_Entry $file ) {
		$this->storage->save( $file );
	}

	public function get_not_imported_files() {
		return $this->storage->find(
			null,
			[
				WPML_ST_Translations_File_Entry::NOT_IMPORTED,
				WPML_ST_Translations_File_Entry::PARTLY_IMPORTED,
			]
		);
	}

	public function clear_skipped() {
		$skipped = wpml_collect( $this->storage->find( null, [ WPML_ST_Translations_File_Entry::SKIPPED ] ) );
		$skipped->each(
			function ( WPML_ST_Translations_File_Entry $entry ) {
				$entry->set_status( WPML_ST_Translations_File_Entry::NOT_IMPORTED );
				$this->storage->save( $entry );
			}
		);
	}

	public function get_imported_files() {
		return $this->storage->find( null, WPML_ST_Translations_File_Entry::IMPORTED );
	}

	public function get_domains( $extension = null, $locale = null ) {
		$files = wpml_collect( $this->storage->find() );

		if ( $extension ) {
			$files = $files->filter( EntryQueries::isExtension( $extension ) );
		}
		if ( $locale ) {
			$files = $files->filter(
				function ( WPML_ST_Translations_File_Entry $file ) use ( $locale ) {
					return $file->get_file_locale() === $locale;
				}
			);
		}

		return $files->map( EntryQueries::getDomain() )
					 ->unique()
					 ->values()
					 ->toArray();
	}

	public function getAllUniquePluginComponentIds( array $extensions = [] ): array {
		return $this->storage->findAllUniqueComponentIds( 'plugin', $extensions );
	}
}
