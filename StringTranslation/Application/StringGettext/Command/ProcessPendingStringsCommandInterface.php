<?php

namespace WPML\StringTranslation\Application\StringGettext\Command;

interface ProcessPendingStringsCommandInterface {
	public function run(): bool;

	public function getLastDeferralDiagnostic(): array;

	public function getLastQuarantineDiagnostic(): array;
}
