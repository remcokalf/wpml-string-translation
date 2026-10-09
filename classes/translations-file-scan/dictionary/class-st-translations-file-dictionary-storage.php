<?php

interface WPML_ST_Translations_File_Dictionary_Storage {

	public function save( WPML_ST_Translations_File_Entry $file );

	public function find( $path = null, $status = null );

	public function findAllUniqueComponentIds( ?string $componentType = null, array $fileExtensions = [] ): array;
}
