<?php

namespace WP_CLI\Fetchers;

class Signup extends Base {

	protected $msg = "Invalid signup ID, email, login, or activation key: '%s'";

	public function get( $signup ) {
		return $this->get_signup( $signup );
	}

	protected function get_signup( $arg ) {
		global $wpdb;

		$signup_object = null;

		if ( is_numeric( $arg ) ) {
			$result = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wpdb->signups WHERE signup_id = %d", $arg ) );

			if ( $result ) {
				$signup_object = $result;
			}
		}

		if ( ! $signup_object ) {
			foreach ( array( 'user_login', 'user_email', 'activation_key' ) as $field ) {
				$result = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wpdb->signups WHERE $field = %s", $arg ) );

				if ( $result ) {
					$signup_object = $result;
					break;
				}
			}
		}

		if ( $signup_object ) {
			return $signup_object;
		}

		return false;
	}
}
