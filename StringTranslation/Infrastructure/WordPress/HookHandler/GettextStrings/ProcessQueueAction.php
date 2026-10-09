<?php
namespace WPML\StringTranslation\Infrastructure\WordPress\HookHandler\GettextStrings;

use WPML\StringTranslation\Application\StringCore\Service\StringsService;
use WPML\StringTranslation\Infrastructure\WordPress\HookHandler\AbstractActionHookHandler;

class ProcessQueueAction extends AbstractActionHookHandler {
	const ACTION_NAME = 'wpml_st_process_queue';

	private $stringsService;

	public function __construct(
		StringsService $stringsService
	) {
		$this->stringsService = $stringsService;
	}

	protected function onAction(...$args) {
		try {
			$this->stringsService->maybeProcessQueue();
		} catch ( \Throwable $processingError ) {
			error_log(
				sprintf(
					'[WPML String Translation] Notice: background string processing paused early and will retry automatically on the next run. No data was lost. Reason: %s (%s:%d)',
					$processingError->getMessage(),
					$processingError->getFile(),
					$processingError->getLine()
				)
			);
		}
	}
}
