<?php

namespace WP_CLI\Bootstrap;

use WP_CLI;
use WP_CLI\Utils;

final class RegisterDeferredCommands implements BootstrapStep {

	public function process( BootstrapState $state ) {

		$this->add_deferred_commands();

		WP_CLI::add_hook(
			'before_run_command',
			[ $this, 'add_deferred_commands' ]
		);

		return $state;
	}

	public function add_deferred_commands() {
		$deferred_additions = WP_CLI::get_deferred_additions();

		foreach ( $deferred_additions as $name => $addition ) {
			$addition_data = [];
			foreach ( $addition as $addition_key => $addition_value ) {
				if ( 'callable' === $addition_key ) {
					$addition_value = Utils\describe_callable( $addition_value );

				} elseif ( is_array( $addition_value ) ) {
					$addition_value = json_encode( $addition_value );
				}

				$addition_data[] = sprintf(
					'%s: %s',
					$addition_key,
					$addition_value
				);
			}

			WP_CLI::debug(
				sprintf(
					'Adding deferred command: %s (%s)',
					$name,
					implode( ', ', $addition_data )
				),
				'bootstrap'
			);

			WP_CLI::add_command(
				$name,
				$addition['callable'],
				$addition['args']
			);
		}
	}
}
