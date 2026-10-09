<?php

namespace WP_CLI;

use Exception;
use RuntimeException;
use WP_CLI;

final class RequestsLibrary {

	const VERSION_V1 = 'v1';

	const VERSION_V2 = 'v2';

	const VALID_VERSIONS = [ self::VERSION_V1, self::VERSION_V2 ];

	const SOURCE_WP_CORE = 'wp-core';

	const SOURCE_WP_CLI = 'wp-cli';

	const VALID_SOURCES = [ self::SOURCE_WP_CORE, self::SOURCE_WP_CLI ];

	const CLASS_NAME_V1 = '\Requests';

	const CLASS_NAME_V2 = '\WpOrg\Requests\Requests';

	private static $version = self::VERSION_V2;

	private static $source = self::SOURCE_WP_CLI;

	private static $class_name = self::CLASS_NAME_V2;

	public static function is_v1() {
		return self::get_version() === self::VERSION_V1;
	}

	public static function is_v2() {
		return self::get_version() === self::VERSION_V2;
	}

	public static function is_core() {
		return self::get_source() === self::SOURCE_WP_CORE;
	}

	public static function is_cli() {
		return self::get_source() === self::SOURCE_WP_CLI;
	}

	public static function get_version() {
		return self::$version;
	}

	public static function set_version( $version ) {
		if ( ! is_string( $version ) ) {
			throw new RuntimeException( 'RequestsLibrary::$version must be a string.' );
		}

		if ( ! in_array( $version, self::VALID_VERSIONS, true ) ) {
			throw new RuntimeException(
				sprintf(
					'Invalid RequestsLibrary::$version, must be one of: %s.',
					implode( ', ', self::VALID_VERSIONS )
				)
			);
		}

		WP_CLI::debug( 'Setting RequestsLibrary::$version to ' . $version, 'bootstrap' );

		self::$version = $version;
	}

	public static function get_class_name() {
		return self::$class_name;
	}

	public static function set_class_name( $class_name ) {
		if ( ! is_string( $class_name ) ) {
			throw new RuntimeException( 'RequestsLibrary::$class_name must be a string.' );
		}

		WP_CLI::debug( 'Setting RequestsLibrary::$class_name to ' . $class_name, 'bootstrap' );

		self::$class_name = $class_name;
	}

	public static function get_source() {
		return self::$source;
	}

	public static function set_source( $source ) {
		if ( ! is_string( $source ) ) {
			throw new RuntimeException( 'RequestsLibrary::$source must be a string.' );
		}

		if ( ! in_array( $source, self::VALID_SOURCES, true ) ) {
			throw new RuntimeException(
				sprintf(
					'Invalid RequestsLibrary::$source, must be one of: %s.',
					implode( ', ', self::VALID_SOURCES )
				)
			);
		}

		WP_CLI::debug( 'Setting RequestsLibrary::$source to ' . $source, 'bootstrap' );

		self::$source = $source;
	}

	public static function is_requests_exception( Exception $exception ) {
		return is_a( $exception, '\Requests_Exception' )
			|| is_a( $exception, '\WpOrg\Requests\Exception' );
	}

	public static function register_autoloader() {
		$includes_path = defined( 'WPINC' ) ? WPINC : 'wp-includes';

		if ( self::is_v1() && ! class_exists( self::CLASS_NAME_V1 ) ) {
			if ( self::is_core() ) {
				require_once ABSPATH . $includes_path . '/class-requests.php';
			} else {
				require_once WP_CLI_VENDOR_DIR . '/rmccue/requests/library/Requests.php';
			}
			\Requests::register_autoloader();
		}

		if ( self::is_v2() && ! class_exists( self::CLASS_NAME_V2 ) ) {
			if ( self::is_core() ) {
				require_once ABSPATH . $includes_path . '/Requests/Autoload.php';
			} else {
				self::maybe_define_wp_cli_root();
				if ( file_exists( WP_CLI_ROOT . '/bundle/rmccue/requests/src/Autoload.php' ) ) {
					require_once WP_CLI_ROOT . '/bundle/rmccue/requests/src/Autoload.php';
				} else {
					require_once WP_CLI_VENDOR_DIR . '/rmccue/requests/src/Autoload.php';
				}
			}
			\WpOrg\Requests\Autoload::register();
		}
	}

	public static function get_bundled_certificate_path() {
		if ( self::is_core() ) {
			$includes_path = defined( 'WPINC' ) ? WPINC : 'wp-includes';
			return ABSPATH . $includes_path . '/certificates/ca-bundle.crt';
		} elseif ( self::is_v1() ) {
			return WP_CLI_VENDOR_DIR . '/rmccue/requests/library/Requests/Transport/cacert.pem';
		} else {
			self::maybe_define_wp_cli_root();
			if ( file_exists( WP_CLI_ROOT . '/bundle/rmccue/requests/certificates/cacert.pem' ) ) {
				return WP_CLI_ROOT . '/bundle/rmccue/requests/certificates/cacert.pem';
			}
			return WP_CLI_VENDOR_DIR . '/rmccue/requests/certificates/cacert.pem';
		}
	}

	private static function maybe_define_wp_cli_root() {
		if ( ! defined( 'WP_CLI_ROOT' ) ) {
			define( 'WP_CLI_ROOT', dirname( dirname( __DIR__ ) ) );
		}
	}
}
