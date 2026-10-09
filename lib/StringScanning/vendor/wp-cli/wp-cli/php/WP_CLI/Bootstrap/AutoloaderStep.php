<?php

namespace WP_CLI\Bootstrap;

use Exception;
use WP_CLI;

abstract class AutoloaderStep implements BootstrapStep {

	protected $state;

	public function process( BootstrapState $state ) {
		$this->state = $state;

		$found_autoloader = false;
		$autoloader_paths = $this->get_autoloader_paths();

		if ( false === $autoloader_paths ) {
			return $state;
		}

		foreach ( $autoloader_paths as $autoloader_path ) {
			if ( is_readable( $autoloader_path ) ) {
				try {
					WP_CLI::debug(
						sprintf(
							'Loading detected autoloader: %s',
							$autoloader_path
						),
						'bootstrap'
					);
					require $autoloader_path;
					$found_autoloader = true;
				} catch ( Exception $exception ) {
					WP_CLI::warning(
						"Failed to load autoloader '{$autoloader_path}'. Reason: "
						. $exception->getMessage()
					);
				}
			}
		}

		if ( ! $found_autoloader ) {
			$this->handle_failure();
		}

		return $this->state;
	}

	protected function get_custom_vendor_folder() {
		$maybe_composer_json = WP_CLI_ROOT . '/../../../composer.json';
		if ( ! is_readable( $maybe_composer_json ) ) {
			return false;
		}

		$composer = json_decode( file_get_contents( $maybe_composer_json ) );

		if ( ! empty( $composer->config )
			&& ! empty( $composer->config->{'vendor-dir'} )
		) {
			return $composer->config->{'vendor-dir'};
		}

		return false;
	}

	protected function handle_failure() { }

	abstract protected function get_autoloader_paths();
}
