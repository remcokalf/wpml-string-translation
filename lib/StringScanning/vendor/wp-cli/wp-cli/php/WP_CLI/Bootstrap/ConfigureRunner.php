<?php

namespace WP_CLI\Bootstrap;

final class ConfigureRunner implements BootstrapStep {

	public function process( BootstrapState $state ) {
		$runner = new RunnerInstance();
		$runner()->init_config();

		$state->setValue( 'config', $runner()->config );
		$state->setValue( 'arguments', $runner()->arguments );
		$state->setValue( 'assoc_args', $runner()->assoc_args );

		return $state;
	}
}
