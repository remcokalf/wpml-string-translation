<?php

namespace WP_CLI\Fetchers;

use WP_CLI;
use WP_User;

class User extends Base {

	protected $msg = "Invalid user ID, email or login: '%s'";

	public function get( $arg ) {

		if ( getenv( 'WP_CLI_FORCE_USER_LOGIN' ) ) {
			$this->msg = "Invalid user login: '%s'";
			return get_user_by( 'login', $arg );
		}

		if ( is_numeric( $arg ) ) {
			$check = get_user_by( 'login', $arg );
			$user  = get_user_by( 'id', $arg );
			if ( $check && $user ) {
				WP_CLI::warning(
					sprintf(
						'Ambiguous user match detected (both ID and user_login exist for identifier \'%d\'). WP-CLI will default to the ID, but you can force user_login instead with WP_CLI_FORCE_USER_LOGIN=1.',
						$arg
					)
				);
			}
		} elseif ( is_email( $arg ) ) {
			$user = get_user_by( 'email', $arg );
			if ( ! $user ) {
				$user = get_user_by( 'login', $arg );
			}
		} else {
			$user = get_user_by( 'login', $arg );
		}

		return $user;
	}
}
