<?php

namespace WP_CLI\Dispatcher;

function get_path( $command ) {
	$path = [];

	do {
		array_unshift( $path, $command->get_name() );
		$command = $command->get_parent();
	} while ( $command );

	return $path;
}
