<?php

namespace WP_CLI\Bootstrap;

use WP_CLI;

final class IncludeFallbackAutoloader extends AutoloaderStep {

	protected function get_autoloader_paths() {
		$autoloader_paths = [
			WP_CLI_VENDOR_DIR . '/autoload.php',
		];

		$custom_vendor = $this->get_custom_vendor_folder();
		if ( false !== $custom_vendor ) {
			array_unshift(
				$autoloader_paths,
				WP_CLI_ROOT . '/../../../' . $custom_vendor . '/autoload.php'
			);
		}

		WP_CLI::debug(
			sprintf(
				'Fallback autoloader paths: %s',
				implode( ', ', $autoloader_paths )
			),
			'bootstrap'
		);

		return $autoloader_paths;
	}
}
