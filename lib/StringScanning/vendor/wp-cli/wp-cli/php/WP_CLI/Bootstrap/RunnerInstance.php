<?php

namespace WP_CLI\Bootstrap;

use WP_CLI;
use WP_CLI\Runner;

final class RunnerInstance {

	public function __invoke() {
		if ( ! class_exists( 'WP_CLI\Runner' ) ) {
			require_once WP_CLI_ROOT . '/php/WP_CLI/Runner.php';
		}

		if ( ! class_exists( 'WP_CLI\Configurator' ) ) {
			require_once WP_CLI_ROOT . '/php/WP_CLI/Configurator.php';
		}

		return WP_CLI::get_runner();
	}
}
