<?php

namespace WP_CLI\Bootstrap;

use WP_CLI\Autoloader;
use WP_CLI\RequestsLibrary;
use WP_CLI\Utils;

final class IncludeRequestsAutoloader implements BootstrapStep {

	const FROM_WP_CORE = 'wp-core';

	const FROM_WP_CLI = 'wp-cli';

	public function process( BootstrapState $state ) {
		if ( class_exists( RequestsLibrary::CLASS_NAME_V2, false ) || class_exists( RequestsLibrary::CLASS_NAME_V1, false ) ) {
			return $state;
		}

		$runner = new RunnerInstance();

		$alias_path = null;
		if ( $runner()->alias
			&& isset( $runner()->aliases[ $runner()->alias ]['path'] ) ) {
			$alias_path = $runner()->aliases[ $runner()->alias ]['path'];
			if ( is_bool( $alias_path ) || empty( $alias_path ) ) {
				return $state;
			}
			if ( ! Utils\is_path_absolute( $alias_path ) ) {
				$alias_path = getcwd() . '/' . $alias_path;
			}
			$wp_root = rtrim( $alias_path, '/' );
		} else {
			$config = $runner()->config;
			if ( isset( $config['path'] ) &&
				( is_bool( $config['path'] ) || empty( $config['path'] ) )
			) {
				return $state;
			}
			$wp_root = rtrim( $runner()->find_wp_root(), '/' );
		}

		if ( file_exists( $wp_root . '/wp-includes/Requests/src/Autoload.php' ) ) {
			if ( ! class_exists( '\\WpOrg\\Requests\\Autoload', false ) ) {
				require_once $wp_root . '/wp-includes/Requests/src/Autoload.php';
			}

			if ( class_exists( '\\WpOrg\\Requests\\Autoload' ) ) {
				\WpOrg\Requests\Autoload::register();
				$this->store_requests_meta( RequestsLibrary::CLASS_NAME_V2, self::FROM_WP_CORE );
				return $state;
			}
		}

		if ( file_exists( $wp_root . '/wp-includes/class-requests.php' ) ) {
			if ( ! class_exists( '\\Requests', false ) ) {
				require_once $wp_root . '/wp-includes/class-requests.php';
			}

			if ( class_exists( '\\Requests' ) ) {
				\Requests::register_autoloader();
				$this->store_requests_meta( RequestsLibrary::CLASS_NAME_V1, self::FROM_WP_CORE );
				return $state;
			}
		}

		$autoloader = new Autoloader();
		$autoloader->add_namespace(
			'WpOrg\Requests',
			WP_CLI_ROOT . '/bundle/rmccue/requests/src'
		);

		$autoloader->register();

		\WpOrg\Requests\Autoload::register();

		$this->store_requests_meta( RequestsLibrary::CLASS_NAME_V2, self::FROM_WP_CLI );

		return $state;
	}

	private function store_requests_meta( $class_name, $source ) {
		RequestsLibrary::set_version(
			RequestsLibrary::CLASS_NAME_V2 === $class_name
				? RequestsLibrary::VERSION_V2
				: RequestsLibrary::VERSION_V1
		);
		RequestsLibrary::set_source( $source );
		RequestsLibrary::set_class_name( $class_name );
	}
}
