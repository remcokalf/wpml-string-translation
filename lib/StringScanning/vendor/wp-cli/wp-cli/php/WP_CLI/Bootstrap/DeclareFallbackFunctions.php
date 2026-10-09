<?php

namespace WP_CLI\Bootstrap;

final class DeclareFallbackFunctions implements BootstrapStep {
	public function process( BootstrapState $state ) {
		include __DIR__ . '/../../fallback-functions.php';

		return $state;
	}
}
