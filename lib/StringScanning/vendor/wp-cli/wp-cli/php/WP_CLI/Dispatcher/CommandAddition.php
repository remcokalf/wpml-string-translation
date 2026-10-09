<?php

namespace WP_CLI\Dispatcher;

use WP_CLI;

final class CommandAddition {

	private $abort = false;

	private $reason = '';

	public function abort( $reason = '' ) {
		$this->abort  = true;
		$this->reason = (string) $reason;
	}

	public function was_aborted() {
		return $this->abort;
	}

	public function get_reason() {
		return $this->reason;
	}
}
