<?php

namespace WPML\StringTranslation\Infrastructure\StringGettext\Command;

use WPML\StringTranslation\Application\StringGettext\Command\SaveProcessedStringsCommandInterface;
use WPML\StringTranslation\Infrastructure\Factory;

class SaveProcessedStringsCommand implements SaveProcessedStringsCommandInterface {

	private $factory;

	public function __construct(
		Factory $factory
	) {
		$this->factory = $factory;
	}

	public function run( string $domain, array $strings ) : bool {
		return $this->factory->getSaveProcessedStringsCommand()->run( $domain, $strings );
	}
}
