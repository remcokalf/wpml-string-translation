<?php

namespace WP_CLI\Bootstrap;

final class LoadDispatcher implements BootstrapStep {

	public function process( BootstrapState $state ) {
		require_once WP_CLI_ROOT . '/php/dispatcher.php';

		return $state;
	}
}
