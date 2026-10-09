<?php

namespace WP_CLI\Context;

use WP_CLI;
use WP_CLI\Context;
use WP_CLI\ContextManager;

final class Auto implements Context {

	const COMMANDS_TO_RUN_AS_ADMIN = [
		[ 'plugin' ],
		[ 'theme' ],
	];

	private $context_manager;

	public function __construct( ContextManager $context_manager ) {
		$this->context_manager = $context_manager;
	}

	public function process( $config ) {
		$config['context'] = $this->deduce_best_context();

		$this->context_manager->switch_context( $config );
	}

	private function deduce_best_context() {
		if ( $this->is_command_to_run_as_admin() ) {
			return Context::ADMIN;
		}

		return Context::CLI;
	}

	private function is_command_to_run_as_admin() {
		$command = WP_CLI::get_runner()->arguments;

		foreach ( self::COMMANDS_TO_RUN_AS_ADMIN as $command_to_run_as_admin ) {
			if (
				array_slice( $command, 0, count( $command_to_run_as_admin ) )
				===
				$command_to_run_as_admin
			) {
				WP_CLI::debug(
					'Detected a command to be intercepted: '
					. implode( ' ', $command ),
					Context::DEBUG_GROUP
				);
				return true;
			}
		}

		return false;
	}
}
