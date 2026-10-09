<?php

namespace WP_CLI\Bootstrap;

final class LaunchRunner implements BootstrapStep {

	public function process( BootstrapState $state ) {
		$runner = new RunnerInstance();

		$runner()->register_context_manager(
			$state->getValue( 'context_manager' )
		);

		$runner()->start();

		return $state;
	}
}
