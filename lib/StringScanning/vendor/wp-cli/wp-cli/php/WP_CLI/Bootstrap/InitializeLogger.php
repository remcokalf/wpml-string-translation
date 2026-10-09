<?php

namespace WP_CLI\Bootstrap;

use DirectoryIterator;

final class InitializeLogger implements BootstrapStep {

	public function process( BootstrapState $state ) {
		$this->declare_loggers();
		$runner = new RunnerInstance();
		$runner()->init_logger();

		return $state;
	}

	private function declare_loggers() {
		$logger_dir = WP_CLI_ROOT . '/php/WP_CLI/Loggers';
		$iterator   = new DirectoryIterator( $logger_dir );

		include_once "$logger_dir/Base.php";

		foreach ( $iterator as $filename ) {
			if ( '.php' !== substr( $filename, - 4 ) ) {
				continue;
			}

			include_once "$logger_dir/$filename";
		}
	}
}
