<?php

namespace WP_CLI\Bootstrap;

final class DeclareMainClass implements BootstrapStep {

	public function process( BootstrapState $state ) {
		require_once WP_CLI_ROOT . '/php/class-wp-cli.php';

		return $state;
	}
}
