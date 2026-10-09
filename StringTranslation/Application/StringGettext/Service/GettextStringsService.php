<?php

namespace WPML\StringTranslation\Application\StringGettext\Service;

use WPML\StringTranslation\Application\StringGettext\Repository\QueueRepositoryInterface;
use WPML\StringTranslation\Application\Setting\Repository\SettingsRepositoryInterface;
use WPML\StringTranslation\Application\StringCore\Repository\TranslationsRepositoryInterface;
use WPML\StringTranslation\Application\StringGettext\Command\ProcessPendingStringsCommandInterface;
use WPML\StringTranslation\Application\StringGettext\Validator\IsExcludedDomainStringValidatorInterface;
use WPML\StringTranslation\Application\Setting\Repository\UrlRepositoryInterface;
use WPML\FP\Str;
use WPML\StringTranslation\Application\StringCore\Domain\StringItem;

class GettextStringsService {

	private $isExcludedDomainStringValidator;

	private $translationsRepository;

	private $queueRepository;

	private $settingsRepository;

	private $processPendingStrings;

	private $urlRepository;

	private $isProcessingString = false;

	public function __construct(
		IsExcludedDomainStringValidatorInterface $isExcludedDomainStringValidator,
		TranslationsRepositoryInterface $translationsRepository,
		QueueRepositoryInterface $queueRepository,
		SettingsRepositoryInterface $settingsRepository,
		ProcessPendingStringsCommandInterface $processPendingStringsCommand,
		UrlRepositoryInterface $urlRepository
	) {
		$this->isExcludedDomainStringValidator = $isExcludedDomainStringValidator;
		$this->translationsRepository          = $translationsRepository;
		$this->queueRepository                 = $queueRepository;
		$this->settingsRepository              = $settingsRepository;
		$this->processPendingStrings           = $processPendingStringsCommand;
		$this->urlRepository                   = $urlRepository;
	}

	public function isAutoregisterEnabled(): bool {
		if ( ! $this->settingsRepository->getIsAutoregistrationEnabled() ) {
			return false;
		}

		if ( $this->settingsRepository->isAutoregisterStringsTypeDisabled() ) {
			return false;
		}

		if ( $this->settingsRepository->isAutoregisterStringsTypeOnlyViewedByAdmin() && ! $this->settingsRepository->getIsCurrentUserAdmin() ) {
			return false;
		}

		return true;
	}

	public function queueStringAsPendingIfUntranslatedOrNotTracked( $text, $domain, $context = '' ) {
		if ( $this->isProcessingString ) {
			return $text;
		}

		$this->isProcessingString = true;

		if (
			! $this->isAutoregisterEnabled() ||
			$this->settingsRepository->shouldNotAutoregisterStringsFromCurrentUrl() ||
			strlen( $text ) === 0 ||
			$this->isExcludedDomainStringValidator->validate( $text, $domain ) ||
			$this->translationsRepository->isTranslationAvailable( $text, $domain, $context )
		) {
			$this->isProcessingString = false;
			return $text;
		}

		$this->queueRepository->addCurrentUrlString( $text, $domain, $context );
		$requestUrl = $this->urlRepository->getClientFrontendRequestUrl();

		if ( $this->queueRepository->isStringAlreadyRegistered( $text, $domain, $context ) ) {
			$this->maybeTrackString( $text, $domain, $requestUrl, $context );
			$this->isProcessingString = false;
			return $text;
		}

		$wasQueued = $this->queueRepository->queueStringAsPending( $text, $domain, $context );
		if ( $wasQueued ) {
			$this->queueRepository->trackString( $text, $domain, $requestUrl, $context );
		}
		$this->isProcessingString = false;

		return $text;
	}

	public function queueCustomStringAsPending( $text, $domain, $context, $name ) {
		if ( $this->isProcessingString ) {
			return $text;
		}

		$this->isProcessingString = true;

		if (
			$this->settingsRepository->isAutoregisterStringsTypeDisabled() ||
			$this->settingsRepository->shouldNotAutoregisterStringsFromCurrentUrl() ||
			strlen( $text ) === 0 ||
			$this->isExcludedDomainStringValidator->validate( $text, $domain )
		) {
			$this->isProcessingString = false;
			return $text;
		}

		$this->queueRepository->addCurrentUrlString( $text, $domain, $context );
		$requestUrl = $this->urlRepository->getClientFrontendRequestUrl();

		if ( $this->queueRepository->isStringAlreadyRegistered( $text, $domain, $context, $name ) ) {
			$this->maybeTrackString( $text, $domain, $requestUrl, $context );
			$this->isProcessingString = false;
			return $text;
		}

		$wasQueued = $this->queueRepository->queueStringAsPending( $text, $domain, $context, $name );
		if (
			$wasQueued &&
			! $this->queueRepository->isStringAlreadyTrackedOnUrl( $text, $domain, $requestUrl, $context )
		) {
			$this->queueRepository->trackString( $text, $domain, $requestUrl, $context );
		}

		$this->isProcessingString = false;
		return $text;
	}

	private function maybeTrackString( string $text, string $domain, string $requestUrl, ?string $context = null ) {
		if (
			$this->queueRepository->isStringAlreadyTrackedOnUrl( $text, $domain, $requestUrl, $context ) ||
			! $this->queueRepository->canTrackString( $text, $domain, $context )
		) {
			return;
		}

		$this->queueRepository->trackString( $text, $domain, $requestUrl, $context );
	}

	public function savePendingStringsQueue() {
		if ( ! $this->isAutoregisterEnabled() ) {
			return;
		}

		$this->queueRepository->savePendingStringsQueue();
	}

	public function processSavedPendingStringsAndSettingsQueue(): bool {
		return $this->processPendingStrings->run();
	}

	public function getLastPendingQueueDeferralDiagnostic(): array {
		return $this->processPendingStrings->getLastDeferralDiagnostic();
	}

	public function getLastPendingQueueQuarantineDiagnostic(): array {
		return $this->processPendingStrings->getLastQuarantineDiagnostic();
	}
}
