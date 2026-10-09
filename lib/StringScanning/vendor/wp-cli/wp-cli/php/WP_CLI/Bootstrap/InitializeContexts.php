<?php

namespace WP_CLI\Bootstrap;

use WP_CLI;
use WP_CLI\Context;
use WP_CLI\ContextManager;

final class InitializeContexts implements BootstrapStep {

	public function process( BootstrapState $state ) {
		$context_manager = new ContextManager();

		$contexts = [
			Context::CLI      => new Context\Cli(),
			Context::ADMIN    => new Context\Admin(),
			Context::FRONTEND => new Context\Frontend(),
			Context::AUTO     => new Context\Auto( $context_manager ),
		];

		$contexts = WP_CLI::do_hook( 'before_registering_contexts', $contexts );

		foreach ( $contexts as $name => $implementation ) {
			$context_manager->register_context( $name, $implementation );
		}

		$state->setValue( 'context_manager', $context_manager );

		return $state;
	}
}
