<?php
namespace WPML\StringTranslation\Infrastructure\WordPress\HookHandler\Wpml;

use WPML\StringTranslation\Infrastructure\WordPress\HookHandler\AbstractActionHookHandler;
use WPML\StringTranslation\Application\StringCore\Command\LoadExistingStringTranslationsForNewLocalesCommandInterface;

class WpmlUpdateActiveLanguagesAction extends AbstractActionHookHandler {
	const ACTION_NAME = 'icl_update_active_languages';
	const ACTION_ARGS = 1;

	private $loadExistingStringTranslationsForNewLocalesCommand;

	public function __construct(
		LoadExistingStringTranslationsForNewLocalesCommandInterface $loadExistingStringTranslationsForNewLocalesCommand
	) {
		$this->loadExistingStringTranslationsForNewLocalesCommand = $loadExistingStringTranslationsForNewLocalesCommand;
	}

	protected function onAction( ...$args ) {
		if ( \WPML\ST\BackgroundTask\LoadExistingTranslationsTask::canBeEnqueued()
			&& \WPML\ST\BackgroundTask\LoadExistingTranslationsTask::enqueue() ) {
			return;
		}

		$this->loadExistingStringTranslationsForNewLocalesCommand->run();
	}
}
