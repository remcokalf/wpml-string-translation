<?php

namespace WPML\StringTranslation\Application\StringCore\Service;

use WPML\StringTranslation\Application\StringGettext\Service\GettextStringsService;

class StringsService {

	private $gettextStringsService;

	public function __construct(
		GettextStringsService $gettextStringsService
	) {
		$this->gettextStringsService = $gettextStringsService;
	}

	public function maybeProcessQueue(): bool {
		if ( ! $this->gettextStringsService->isAutoregisterEnabled() ) {
			return true;
		}

		return $this->gettextStringsService->processSavedPendingStringsAndSettingsQueue();
	}

	public function getLastQueueDeferralDiagnostic(): array {
		return $this->gettextStringsService->getLastPendingQueueDeferralDiagnostic();
	}

	public function getLastQueueQuarantineDiagnostic(): array {
		return $this->gettextStringsService->getLastPendingQueueQuarantineDiagnostic();
	}
}
