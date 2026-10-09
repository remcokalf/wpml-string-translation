<?php
namespace WPML\StringTranslation\Infrastructure\WordPress\HookHandler;

use WPML\StringTranslation\Application\Setting\Repository\SettingsRepositoryInterface;
use WPML\StringTranslation\Application\Setting\Repository\UrlRepositoryInterface;
use WPML\FP\Str;
use WPML\StringTranslation\Application\StringHtml\Service\HtmlStringsService;
use WPML\StringTranslation\Application\StringGettext\Service\GettextStringsService;
use WPML\StringTranslation\Application\StringCore\Domain\StringItem;

class InitAction extends AbstractActionHookHandler {
	const ACTION_NAME     = 'init';
	const ACTION_ARGS     = 0;
	const ACTION_PRIORITY = 0;

	private $settingsRepository;

	private $urlRepository;

	private $htmlStringsService;

	private $gettextStringsService;

	public function __construct(
		SettingsRepositoryInterface $settingsRepository,
		UrlRepositoryInterface $urlRepository,
		HtmlStringsService $htmlStringsService,
		GettextStringsService $gettextStringsService
	) {
		$this->settingsRepository    = $settingsRepository;
		$this->urlRepository         = $urlRepository;
		$this->htmlStringsService    = $htmlStringsService;
		$this->gettextStringsService = $gettextStringsService;
	}

	protected function onAction( ...$args ) {
		$this->settingsRepository->updateIfIsCurrentUserAdminCache();

		$this->settingsRepository->setIsAutoregistrationEnabled( true );


		if (
			$this->gettextStringsService->isAutoregisterEnabled() &&
			! $this->settingsRepository->shouldNotAutoregisterStringsFromCurrentUrl()
		) {
			$this->htmlStringsService->startCapturingBuffer();
		}
	}
}
