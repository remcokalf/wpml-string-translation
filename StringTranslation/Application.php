<?php

namespace WPML\StringTranslation;

use WPML\Auryn\Injector;
use WPML\StringTranslation\Application\StringGettext\Repository\QueueRepositoryInterface;
use WPML\StringTranslation\Application\StringGettext\Repository\LoadedTextdomainRepositoryInterface;
use WPML\StringTranslation\Application\Setting\Repository\SettingsRepositoryInterface;
use WPML\StringTranslation\Application\StringHtml\Command\ProcessFrontendGettextStringsQueueInterface;
use WPML\StringTranslation\Infrastructure\StringHtml\Command\ProcessFrontendGettextStringsQueue;
use WPML\StringTranslation\Infrastructure\TranslateEverything\ProcessFrontendStringsObserver;
use WPML\StringTranslation\Infrastructure\TranslateEverything\UntranslatedStrings;
use WPML\StringTranslation\Infrastructure\TranslateEverything\UntranslatedStringsFactory;
use WPML\StringTranslation\Infrastructure\WordPress\HookHandler\HookHandlerInterface;
use WPML\StringTranslation\Application\WordPress\HookHandler\AutoregisterHookInterface;
use WPML\StringTranslation\Infrastructure\Factory;
use WPML\ST\Gettext\AutoRegisterSettings;
use WPML\TM\AutomaticTranslation\Actions\Actions;

class Application {

	private $implementations;

	private $hookHandlers;

	private $settings;

	private $injector;

	public function __construct( array $config = [] ) {
		$this->implementations = array_key_exists( 'interfaceMappings', $config ) ? $config['interfaceMappings'] : [];
		$this->hookHandlers    = array_key_exists( 'hookHandlers', $config ) ? $config['hookHandlers'] : [];
		$this->settings        = array_key_exists( 'settings', $config ) ? $config['settings'] : [];

		global $sitepress;
		global $wpdb;
		$filesystem = wpml_get_filesystem();

		$this->injector = new Injector();
		$this->injector->share( QueueRepositoryInterface::class );
		$this->injector->share( \WPML\StringTranslation\Application\StringCore\Command\SaveStringsGuardedCommandInterface::class );
		$this->injector->share( \WPML\StringTranslation\Application\StringCore\Command\InsertStringTranslationsGuardedCommandInterface::class );
		$this->injector->share( LoadedTextdomainRepositoryInterface::class );
		$this->injector->share( SettingsRepositoryInterface::class );
		$this->injector->defineParam( 'sitepress', $sitepress );
		$this->injector->defineParam( 'wpdb', $wpdb );
		$this->injector->defineParam( 'filesystem', $filesystem );

		$this->injector->delegate(
			\WP_Filesystem_Base::class,
			function () use ( $filesystem ) {
				return $filesystem;
			}
		);

		$this->injector->defineParam(
			'getCompletedTranslationService',
			function () {
				return \WPML\Translation\CompletedTranslationServiceFactory::create();
			}
		);

		foreach ( $this->implementations as $interfaceClass => $implementationClass ) {
			$this->injector->alias( $interfaceClass, $implementationClass );
		}

		$this->injector->delegate( AutoRegisterSettings::class, function () {
			return \WPML\Container\make( AutoRegisterSettings::class );
		} );

		$this->injector->delegate( ProcessFrontendGettextStringsQueueInterface::class, function () use ( $sitepress ) {
			$translateEverythingObserver = new ProcessFrontendStringsObserver(
				( new UntranslatedStringsFactory() )->create(),
				new Actions( new \WPML_Translation_Element_Factory( $sitepress, null ) )
			);

			return $this->injector->make( ProcessFrontendGettextStringsQueue::class, [
				':observers' => [ $translateEverythingObserver ]
			] );
		} );

		$factory = new Factory( $this->injector );
		$this->injector->share( $factory );
	}

	public function run() {
		$settingsRepository = $this->injector->make( SettingsRepositoryInterface::class );
		$ignoreIsDisabled   = $this->settings['ignoreIsDisabled'] ?? false;
		$isDisabled         = (
			$settingsRepository->isAutoregisterStringsTypeDisabled() ||
			$settingsRepository->shouldNotAutoregisterStringsFromCurrentUrl()
		);

		foreach ( $this->hookHandlers as $hookHandler ) {
			$hookHandler = $this->injector->make( $hookHandler );
			if ( $hookHandler instanceof HookHandlerInterface ) {
				if ( $isDisabled && ! $ignoreIsDisabled && $hookHandler instanceof AutoregisterHookInterface ) {
					continue;
				}
				$hookHandler->load();
			}
		}
	}
}