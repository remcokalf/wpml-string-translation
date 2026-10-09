<?php

namespace WP_CLI\Bootstrap;

use WP_CLI;

final class LoadExecCommand implements BootstrapStep {

	public function process( BootstrapState $state ) {
		if ( $state->getValue( BootstrapState::IS_PROTECTED_COMMAND, false ) ) {
			return $state;
		}

		$runner = new RunnerInstance();
		if ( ! isset( $runner()->config['exec'] ) ) {
			return $state;
		}

		foreach ( $runner()->config['exec'] as $php_code ) {
			eval( $php_code );
		}

		return $state;
	}
}
