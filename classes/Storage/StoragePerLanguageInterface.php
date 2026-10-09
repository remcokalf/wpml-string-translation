<?php

namespace WPML\ST\Storage;

interface StoragePerLanguageInterface {
	const NOTHING = '___NOTHING___';

	const GLOBAL_GROUP = '__ALL_LANG__';


	public function get( $lang );


	public function save( $lang, $value );


	public function delete( $lang );


}
