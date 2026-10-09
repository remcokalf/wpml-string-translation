<?php

namespace WP_CLI\Fetchers;

use WP_CLI;
use WP_CLI\ExitException;

abstract class Base {

	protected $msg;

	abstract public function get( $arg );

	public function get_check( $arg ) {
		$item = $this->get( $arg );

		if ( ! $item ) {
			WP_CLI::error( sprintf( $this->msg, $arg ) );
		}

		return $item;
	}

	public function get_many( $args ) {
		$items = [];

		foreach ( $args as $arg ) {
			$item = $this->get( $arg );

			if ( $item ) {
				$items[] = $item;
			} else {
				WP_CLI::warning( sprintf( $this->msg, $arg ) );
			}
		}

		return $items;
	}
}
