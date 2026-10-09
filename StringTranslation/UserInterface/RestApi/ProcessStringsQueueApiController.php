<?php
namespace WPML\StringTranslation\UserInterface\RestApi;

use WPML\Rest\Adaptor;
use WPML\StringTranslation\Application\StringCore\Service\StringsService;
use WPML\StringTranslation\Application\StringHtml\Service\HtmlStringsService;
use WPML\StringTranslation\Application\StringGettext\Service\GettextStringsService;
use WPML\StringTranslation\Application\StringGettext\Repository\QueueRepositoryInterface;
use WPML\StringTranslation\Application\StringGettext\Repository\FrontendQueueRepositoryInterface;
use WPML\StringTranslation\Application\Setting\Repository\SettingsRepositoryInterface;
use WPML\Utilities\Lock;
use function WPML\Container\make;

class ProcessStringsQueueApiController extends AbstractController {

	private $stringsService;

	private $htmlStringsService;

	private $gettextStringsService;

	private $queueRepository;

	private $frontendQueueRepository;

	private $settingsRepository;

	public function __construct(
		Adaptor $adaptor,
		StringsService $stringsService,
		HtmlStringsService $htmlStringsService,
		GettextStringsService $gettextStringsService,
		QueueRepositoryInterface $queueRepository,
		FrontendQueueRepositoryInterface $frontendQueueRepository,
		SettingsRepositoryInterface $settingsRepository
	) {
		parent::__construct( $adaptor );
		$this->stringsService          = $stringsService;
		$this->htmlStringsService      = $htmlStringsService;
		$this->gettextStringsService   = $gettextStringsService;
		$this->queueRepository         = $queueRepository;
		$this->frontendQueueRepository = $frontendQueueRepository;
		$this->settingsRepository      = $settingsRepository;
	}

	public function get_routes() {
		return [
			[
				'route' => 'strings/processstringsqueue',
				'args'  => [
					'methods'  => 'POST',
					'callback' => [ $this, 'post' ],
				],
			],
		];
	}

	public function post( \WP_REST_Request $request ) {
		if ( ! $this->gettextStringsService->isAutoregisterEnabled() ) {
			return [
				'wasProcessed'   => false,
				'hasPending'     => false,
				'shouldContinue' => false,
			];
		}

		$lock    = make( Lock::class, [ ':name' => 'processstringsqueue' ] );
		$hasLock = $lock->create( 60 );

		if ( ! $hasLock ) {
			return [
				'wasProcessed'   => false,
				'hasPending'     => true,
				'shouldContinue' => true,
				'workerBusy'     => true,
			];
		}

		$processedHtmlUrlGroups = 0;
		$quarantineDiagnostic   = [];

		try {
			$hasPendingGettextStrings = $this->queueRepository->hasPendingStrings();
			$pendingHtmlUrlGroups     = $this->frontendQueueRepository->count();
			$hasPendingHtmlStrings    = $pendingHtmlUrlGroups > 0;
			$deferralDiagnostic       = [];
			$gettextQueueWasProcessed = true;

			if ( $hasPendingGettextStrings ) {
				$gettextQueueWasProcessed = $this->stringsService->maybeProcessQueue();
				$deferralDiagnostic       = $this->stringsService->getLastQueueDeferralDiagnostic();
				$quarantineDiagnostic     = $this->stringsService->getLastQueueQuarantineDiagnostic();
			}
			if ( $hasPendingHtmlStrings && $gettextQueueWasProcessed ) {
				$processedHtmlUrlGroups = $this->htmlStringsService->maybeProcessFrontendGettextStringsQueue();
			}

			$wasProcessed = $hasPendingGettextStrings || $hasPendingHtmlStrings;
			if ( $this->settingsRepository->wereNewTranslationsLoaded() ) {
				$wasProcessed = true;
				$this->settingsRepository->unsetNewTranslationsWereLoadedSetting();
			}

			$remainingHtmlUrlGroups = $this->frontendQueueRepository->count();
			$hasPending             = $this->queueRepository->hasPendingStrings()
				|| $remainingHtmlUrlGroups > 0;
			$canRetryInFreshRequest = ! isset( $deferralDiagnostic['retry_fresh_request'] )
				|| (bool) $deferralDiagnostic['retry_fresh_request'];

			return [
				'wasProcessed'         => $wasProcessed,
				'hasPending'           => $hasPending,
				'shouldContinue'       => $hasPending && $canRetryInFreshRequest,
				'retryFreshRequest'    => $canRetryInFreshRequest,
				'quarantinedStrings'   => isset( $quarantineDiagnostic['count'] ) ? (int) $quarantineDiagnostic['count'] : 0,
				'pagesProcessed'       => $processedHtmlUrlGroups,
				'pagesRemaining'       => $remainingHtmlUrlGroups,
			];
		} catch ( \Throwable $processingError ) {
			error_log(
				sprintf(
					'[WPML String Translation] Notice: background string processing paused early and will retry automatically on the next run. No data was lost. Reason: %s (%s:%d)',
					$processingError->getMessage(),
					$processingError->getFile(),
					$processingError->getLine()
				)
			);

			return [
				'wasProcessed'       => false,
				'hasPending'         => true,
				'shouldContinue'     => false,
				'retryFreshRequest'  => true,
				'error'              => true,
				'quarantinedStrings' => isset( $quarantineDiagnostic['count'] ) ? (int) $quarantineDiagnostic['count'] : 0,
				'pagesProcessed'     => $processedHtmlUrlGroups,
			];
		} finally {
			$lock->release();
		}
	}

}
