<?php

namespace WP_CLI;

use WP_CLI\Bootstrap\BootstrapState;
use WP_CLI\Bootstrap\BootstrapStep;

function get_bootstrap_steps() {
	return [
		Bootstrap\DeclareFallbackFunctions::class,
		Bootstrap\LoadUtilityFunctions::class,
		Bootstrap\LoadDispatcher::class,
		Bootstrap\DeclareMainClass::class,
		Bootstrap\DeclareAbstractBaseCommand::class,
		Bootstrap\IncludeFrameworkAutoloader::class,
		Bootstrap\ConfigureRunner::class,
		Bootstrap\InitializeColorization::class,
		Bootstrap\InitializeLogger::class,
		Bootstrap\CheckRoot::class,
		Bootstrap\IncludeRequestsAutoloader::class,
		Bootstrap\DefineProtectedCommands::class,
		Bootstrap\LoadExecCommand::class,
		Bootstrap\LoadRequiredCommand::class,
		Bootstrap\IncludePackageAutoloader::class,
		Bootstrap\IncludeFallbackAutoloader::class,
		Bootstrap\RegisterFrameworkCommands::class,
		Bootstrap\RegisterDeferredCommands::class,
		Bootstrap\InitializeContexts::class,
		Bootstrap\LaunchRunner::class,
	];
}

function prepare_bootstrap() {
	require_once WP_CLI_ROOT . '/php/WP_CLI/Autoloader.php';

	$autoloader = new Autoloader();

	$autoloader->add_namespace(
		'WP_CLI\Bootstrap',
		WP_CLI_ROOT . '/php/WP_CLI/Bootstrap'
	)->register();
}

function initialize_bootstrap_state() {
	return new BootstrapState();
}

function bootstrap() {
	prepare_bootstrap();
	$state = initialize_bootstrap_state();

	foreach ( get_bootstrap_steps() as $step ) {
		if ( class_exists( 'WP_CLI' ) ) {
			\WP_CLI::debug( "Processing bootstrap step: {$step}", 'bootstrap' );
		}

		$step_instance = new $step();
		$state         = $step_instance->process( $state );
	}
}
