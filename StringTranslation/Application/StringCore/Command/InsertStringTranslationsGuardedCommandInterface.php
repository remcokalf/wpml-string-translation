<?php

namespace WPML\StringTranslation\Application\StringCore\Command;

use WPML\StringTranslation\Application\StringCore\Domain\StringTranslation;

interface InsertStringTranslationsGuardedCommandInterface {

	public function run( array $translations ): int;

	public function getLastQuarantineDiagnostic(): array;
}
