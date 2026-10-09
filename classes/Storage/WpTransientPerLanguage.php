<?php

namespace WPML\ST\Storage;

class WpTransientPerLanguage implements StoragePerLanguageInterface {

	private $id;

	private $lifetime = 86400;

	public function __construct( $id ) {
		$this->id = $id;
	}


	public function get( $lang ) {
		$value = get_transient( $this->getName( $lang ) );

		return false === $value
			? StoragePerLanguageInterface::NOTHING
			: $value;
	}


	public function save( $lang, $value ) {
		return set_transient( $this->getName( $lang ), $value, $this->lifetime );
	}


	public function delete( $lang ) {
		return delete_transient( $this->getName( $lang ) );
	}



	public function setLifetime( $lifetime ) {
		$lifetime = (int) $lifetime;

		if ( $lifetime <= 0 ) {
			$lifetime = 86400 * 365;
		}

		$this->lifetime = $lifetime;
	}

	private function getName( $lang ) {
		return $this->id . '-' . $lang;
	}

}

