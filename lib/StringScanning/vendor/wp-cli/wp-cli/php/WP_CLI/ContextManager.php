<?php

namespace WP_CLI;

use WP_CLI;

final class ContextManager {

	private $contexts = [];

	private $current_context = Context::CLI;

	public function register_context( $name, Context $implementation ) {
		$this->contexts[ $name ] = $implementation;
	}

	public function switch_context( $config ) {
		$context = isset( $config['context'] )
			? $config['context']
			: $this->current_context;

		if ( ! array_key_exists( $context, $this->contexts ) ) {
			WP_CLI::error( "Unknown context '{$context}'" );
		}

		WP_CLI::debug( "Using context '{$context}'", Context::DEBUG_GROUP );

		$this->current_context = $context;
		$this->contexts[ $context ]->process( $config );
	}

	public function get_context() {
		return $this->current_context;
	}
}
