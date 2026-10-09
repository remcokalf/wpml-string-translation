<?php
namespace WPML\StringTranslation\Infrastructure\WordPress\HookHandler;

use WPML\StringTranslation\Application\StringGettext\Service\GettextStringsService;
use WPML\StringTranslation\Application\Setting\Repository\SettingsRepositoryInterface;

class ShutdownAction extends AbstractActionHookHandler {
	const ACTION_NAME = 'shutdown';

	private $gettextStringsService;

	private $settingsRepository;

	public function __construct(
		GettextStringsService        $gettextStringsService,
		SettingsRepositoryInterface  $settingsRepository
	) {
		$this->gettextStringsService = $gettextStringsService;
		$this->settingsRepository    = $settingsRepository;
	}

	protected function onAction( ...$args ) {
		$this->gettextStringsService->savePendingStringsQueue();
	}
}
