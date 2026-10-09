<?php

namespace WPML\StringTranslation\Infrastructure\StringGettext\Validator\IsExcludedDomainString;

use WPML\StringTranslation\Application\StringGettext\Validator\IsExcludedDomainStringValidatorInterface;

class FromAuthorsDomainValidator implements IsExcludedDomainStringValidatorInterface {

	public function validate( string $text, string $domain ): bool {
		return $domain === 'Authors';
	}
}