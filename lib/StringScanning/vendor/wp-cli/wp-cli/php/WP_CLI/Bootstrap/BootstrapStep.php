<?php

namespace WP_CLI\Bootstrap;

interface BootstrapStep {

	public function process( BootstrapState $state );
}
