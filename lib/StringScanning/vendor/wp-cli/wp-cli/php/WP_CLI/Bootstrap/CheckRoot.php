<?php

namespace WP_CLI\Bootstrap;

use WP_CLI;
use WP_CLI\Utils;

class CheckRoot implements BootstrapStep {

	public function process( BootstrapState $state ) {
		$config = $state->getValue( 'config', [] );
		if ( array_key_exists( 'allow-root', $config ) && true === $config['allow-root'] ) {
			return $state;
		}

		if ( getenv( 'WP_CLI_ALLOW_ROOT' ) ) {
			return $state;
		}

		$args = $state->getValue( 'arguments', [] );
		if ( count( $args ) >= 2 && 'cli' === $args[0] && in_array( $args[1], [ 'update', 'info' ], true ) ) {
			return $state;
		}

		if ( ! function_exists( 'posix_geteuid' ) ) {
			return $state;
		}

		if ( posix_geteuid() !== 0 ) {
			return $state;
		}

		WP_CLI::error(
			"YIKES! It looks like you're running this as root. You probably meant to " .
			"run this as the user that your WordPress installation exists under.\n" .
			"\n" .
			"If you REALLY mean to run this as root, we won't stop you, but just " .
			'bear in mind that any code on this site will then have full control of ' .
			"your server, making it quite DANGEROUS.\n" .
			"\n" .
			"If you'd like to continue as root, please run this again, adding this " .
			"flag:  --allow-root\n" .
			"\n" .
			"If you'd like to run it as the user that this site is under, you can " .
			"run the following to become the respective user:\n" .
			"\n" .
			"    sudo -u USER -i -- wp <command>\n" .
			"\n"
		);
	}
}
