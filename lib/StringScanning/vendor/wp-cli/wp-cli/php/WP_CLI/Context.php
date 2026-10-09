<?php

namespace WP_CLI;

interface Context {

	const ADMIN    = 'admin';
	const AUTO     = 'auto';
	const CLI      = 'cli';
	const FRONTEND = 'frontend';

	const DEBUG_GROUP = 'context';

	public function process( $config );
}
