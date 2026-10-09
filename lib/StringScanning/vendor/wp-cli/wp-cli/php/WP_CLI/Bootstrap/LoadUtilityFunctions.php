<?php

namespace WP_CLI\Bootstrap;

final class LoadUtilityFunctions implements BootstrapStep {

	public function process( BootstrapState $state ) {
		require_once WP_CLI_ROOT . '/php/utils.php';

		return $state;
	}
}
