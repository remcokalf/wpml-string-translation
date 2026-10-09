<?php

namespace WP_CLI\Bootstrap;

final class DefineProtectedCommands implements BootstrapStep {

	public function process( BootstrapState $state ) {
		$commands        = $this->get_protected_commands();
		$current_command = $this->get_current_command();

		foreach ( $commands as $command ) {
			if ( 0 === strpos( $current_command, $command ) ) {
				$state->setValue( BootstrapState::IS_PROTECTED_COMMAND, true );
			}
		}

		return $state;
	}

	private function get_protected_commands() {
		return [
			'cli info',
			'package',
		];
	}

	private function get_current_command() {
		$runner = new RunnerInstance();

		return implode( ' ', (array) $runner()->arguments );
	}
}
