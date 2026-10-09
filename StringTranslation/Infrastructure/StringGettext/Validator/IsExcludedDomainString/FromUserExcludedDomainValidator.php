<?php

namespace WPML\StringTranslation\Infrastructure\StringGettext\Validator\IsExcludedDomainString;

use WPML\ST\Gettext\AutoRegisterSettings;
use WPML\StringTranslation\Application\StringGettext\Validator\IsExcludedDomainStringValidatorInterface;

class FromUserExcludedDomainValidator implements IsExcludedDomainStringValidatorInterface {

	private $autoRegisterSettings;

	public function __construct( AutoRegisterSettings $autoRegisterSettings ) {
		$this->autoRegisterSettings = $autoRegisterSettings;
	}

	public function validate( string $text, string $domain ): bool {
		return $this->autoRegisterSettings->isExcludedDomain( $domain );
	}
}
