<?php

namespace WP_CLI;

final class NoOp {

	public function __set( $key, $value ) {
	}

	public function __call( $method, $args ) {
	}
}
