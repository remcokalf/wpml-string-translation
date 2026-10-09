<?php


namespace WPML\ST\MO\File;

use WPML\ST\TranslationFile\StringEntity;

class Builder extends \WPML\ST\TranslationFile\Builder {

	private $generator;

	public function __construct( Generator $generator ) {
		$this->generator = $generator;
	}
	
	public function get_content( array $strings ) {
		return $this->generator->getContent( $strings );
	}
}