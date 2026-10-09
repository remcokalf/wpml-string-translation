<?php

namespace WP_CLI\Bootstrap;

final class InitializeColorization implements BootstrapStep {

	public function process( BootstrapState $state ) {
		$runner = new RunnerInstance();
		$runner()->init_colorization();

		return $state;
	}
}
