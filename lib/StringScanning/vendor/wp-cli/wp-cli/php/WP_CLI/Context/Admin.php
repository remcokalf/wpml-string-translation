<?php

namespace WP_CLI\Context;

use WP_CLI;
use WP_CLI\Context;
use WP_Session_Tokens;

final class Admin implements Context {

	public function process( $config ) {
		if ( defined( 'WP_ADMIN' ) ) {
			if ( ! WP_ADMIN ) {
				WP_CLI::warning( 'Could not fake admin request.' );
			}

			return;
		}

		WP_CLI::debug( 'Faking an admin request', Context::DEBUG_GROUP );

		define( 'WP_ADMIN', true );

		$_SERVER['PHP_SELF'] = '/wp-admin/wp-cli-fake-admin-file.php';

		WP_CLI::add_wp_hook(
			'init',
			function () {
				$this->log_in_as_admin_user();
				$this->load_admin_environment();
			},
			defined( 'PHP_INT_MIN' ) ? PHP_INT_MIN : -2147483648,
			0
		);
	}

	private function log_in_as_admin_user() {
		$admin_user_id = 1;

		wp_set_current_user( $admin_user_id );

		$expiration = time() + DAY_IN_SECONDS;

		$_COOKIE[ AUTH_COOKIE ] = wp_generate_auth_cookie(
			$admin_user_id,
			$expiration,
			'auth'
		);

		$_COOKIE[ SECURE_AUTH_COOKIE ] = wp_generate_auth_cookie(
			$admin_user_id,
			$expiration,
			'secure_auth'
		);
	}

	private function load_admin_environment() {
		global $hook_suffix, $pagenow, $wp_db_version, $_wp_submenu_nopriv;

		if ( ! isset( $hook_suffix ) ) {
			$hook_suffix = 'index';
		}

		$wp_db_version = (int) get_option( 'db_version' );

		if ( ! isset( $_wp_submenu_nopriv ) ) {
			$_wp_submenu_nopriv = [];
		}

		$admin_php_file = file_get_contents( ABSPATH . 'wp-admin/admin.php' );

		$admin_php_file = preg_replace( '/^<\?php\s+/', '', $admin_php_file );
		$admin_php_file = preg_replace( '/\s+\?>$/', '', $admin_php_file );

		$admin_php_file = preg_replace( '/^\s*(?:include|require).*[\'"]\/?wp-(?:load|config)\.php[\'"]\s*\)?;\s*$/m', '', $admin_php_file );

		$admin_php_file = preg_replace( '/^\s*auth_redirect\(\);$/m', '', $admin_php_file );

		$admin_php_file   = preg_replace( '/^\s*nocache_headers\(\);$/m', '', $admin_php_file );
		$_GET['noheader'] = true;

		eval( $admin_php_file );
	}
}
