<?php

namespace WP_CLI\Dispatcher;

use WP_CLI\Utils;

class RootCommand extends CompositeCommand {

	public function __construct() {
		$this->parent = false;

		$this->name = 'wp';

		$this->shortdesc = 'Manage WordPress through the command-line.';
	}

	public function get_longdesc() {
		return $this->get_global_params( true );
	}

	public function find_subcommand( &$args ) {
		$command = array_shift( $args );

		Utils\load_command( $command );

		if ( ! isset( $this->subcommands[ $command ] ) ) {
			return false;
		}

		return $this->subcommands[ $command ];
	}
}
