<?php

namespace WP_CLI\Bootstrap;

use WP_CLI\Autoloader;

final class IncludeFrameworkAutoloader implements BootstrapStep {

	public function process( BootstrapState $state ) {
		if ( ! class_exists( 'WP_CLI\Autoloader' ) ) {
			require_once WP_CLI_ROOT . '/php/WP_CLI/Autoloader.php';
		}

		$autoloader = new Autoloader();

		$mappings = [
			'WP_CLI'                   => WP_CLI_ROOT . '/php/WP_CLI',
			'cli'                      => WP_CLI_VENDOR_DIR . '/wp-cli/php-cli-tools/lib/cli',
			'Symfony\Component\Finder' => WP_CLI_VENDOR_DIR . '/symfony/finder/',
		];

		foreach ( $mappings as $namespace => $folder ) {
			$autoloader->add_namespace(
				$namespace,
				$folder
			);
		}

		include_once WP_CLI_VENDOR_DIR . '/wp-cli/mustangostang-spyc/Spyc.php';

		$autoloader->register();

		return $state;
	}
}
