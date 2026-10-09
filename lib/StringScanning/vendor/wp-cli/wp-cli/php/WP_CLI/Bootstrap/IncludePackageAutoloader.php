<?php

namespace WP_CLI\Bootstrap;

use WP_CLI;

final class IncludePackageAutoloader extends AutoloaderStep {

	protected function get_autoloader_paths() {
		if ( $this->state->getValue( BootstrapState::IS_PROTECTED_COMMAND, false ) ) {
			return false;
		}

		$runner        = new RunnerInstance();
		$skip_packages = $runner()->config['skip-packages'];
		if ( true === $skip_packages ) {
			WP_CLI::debug( 'Skipped loading packages.', 'bootstrap' );

			return false;
		}

		$autoloader_path = $runner()->get_packages_dir_path() . 'vendor/autoload.php';

		if ( is_readable( $autoloader_path ) ) {
			WP_CLI::debug(
				'Loading packages from: ' . $autoloader_path,
				'bootstrap'
			);

			return [
				$autoloader_path,
			];
		}

		return false;
	}

	protected function handle_failure() {
		WP_CLI::debug( 'No package autoload found to load.', 'bootstrap' );
	}
}
