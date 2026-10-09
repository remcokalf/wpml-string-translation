<?php

namespace WP_CLI\Bootstrap;

class BootstrapState {

	const IS_PROTECTED_COMMAND = 'is_protected_command';

	private $state = [];

	public function getValue( $key, $fallback = null ) {
		return array_key_exists( $key, $this->state )
			? $this->state[ $key ]
			: $fallback;
	}

	public function setValue( $key, $value ) {
		$this->state[ $key ] = $value;
	}
}
