<?php

class CLI_Cache_Command extends WP_CLI_Command {

	public function cache_clear() {
		$cache = WP_CLI::get_cache();

		if ( ! $cache->is_enabled() ) {
			WP_CLI::error( 'Cache directory does not exist.' );
		}

		$cache->clear();

		WP_CLI::success( 'Cache cleared.' );
	}

	public function cache_prune() {
		$cache = WP_CLI::get_cache();

		if ( ! $cache->is_enabled() ) {
			WP_CLI::error( 'Cache directory does not exist.' );
		}

		$cache->prune();

		WP_CLI::success( 'Cache pruned.' );
	}
}
