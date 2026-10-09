<?php

namespace WPML\StringTranslation\Application\StringCore\Command;

use WPML\StringTranslation\Application\StringCore\Domain\StringItem;

interface SaveStringsGuardedCommandInterface {

	public function run( string $domain, array $strings ): array;

	public function getLastQuarantineDiagnostic(): array;
}
