<?php

class WPML_ST_Path_Confinement {

	public static function resolve_contained( $path, $root, $allow_root = false ) {
		$canonical_root = realpath( $root );
		if ( false === $canonical_root ) {
			return false;
		}

		$canonical_path = realpath( $path );
		if ( false === $canonical_path ) {
			return false;
		}

		if ( $allow_root && $canonical_path === $canonical_root ) {
			return $canonical_path;
		}

		if ( strpos( $canonical_path, $canonical_root . DIRECTORY_SEPARATOR ) === 0 ) {
			return $canonical_path;
		}

		return false;
	}

	public static function resolve_registered_plugin_file( $plugin_file ) {
		if ( ! self::is_safe_plugin_file_identifier( $plugin_file ) ) {
			return false;
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugins = (array) get_plugins();
		if ( ! array_key_exists( $plugin_file, $plugins ) ) {
			return false;
		}

		$logical_file   = WP_PLUGIN_DIR . '/' . $plugin_file;
		$canonical_file = realpath( $logical_file );

		if ( false === $canonical_file || ! is_file( $canonical_file ) || ! is_readable( $canonical_file ) ) {
			return false;
		}

		$plugin_dir = dirname( $plugin_file );
		if ( '.' !== $plugin_dir ) {
			$canonical_root = realpath( WP_PLUGIN_DIR . '/' . $plugin_dir );
			if ( false === $canonical_root || false === self::resolve_contained( $canonical_file, $canonical_root ) ) {
				return false;
			}
		}

		return $canonical_file;
	}

	public static function resolve_registered_plugin_root( $plugin_file ) {
		if ( ! self::is_safe_plugin_file_identifier( $plugin_file ) ) {
			return false;
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugins = (array) get_plugins();
		if ( ! array_key_exists( $plugin_file, $plugins ) ) {
			return false;
		}

		$plugin_dir = dirname( $plugin_file );
		if ( '.' === $plugin_dir ) {
			return self::resolve_registered_plugin_file( $plugin_file );
		}

		$canonical_root = realpath( WP_PLUGIN_DIR . '/' . $plugin_dir );

		return false !== $canonical_root && is_dir( $canonical_root ) && is_readable( $canonical_root )
			? $canonical_root
			: false;
	}

	public static function resolve_registered_plugin_id_for_path( $path ) {
		if ( ! is_string( $path ) || '' === $path || false === realpath( $path ) ) {
			return false;
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		foreach ( array_keys( (array) get_plugins() ) as $plugin_file ) {
			$canonical_root = self::resolve_registered_plugin_root( $plugin_file );
			if ( false !== $canonical_root && false !== self::resolve_contained( $path, $canonical_root, true ) ) {
				return $plugin_file;
			}
		}

		return false;
	}

	public static function resolve_registered_plugin_logical_path( $path, $plugin_file ) {
		$canonical_root = self::resolve_registered_plugin_root( $plugin_file );
		if ( false === $canonical_root ) {
			return false;
		}

		$canonical_path = self::resolve_contained( $path, $canonical_root, true );
		if ( false === $canonical_path ) {
			return false;
		}

		$plugin_dir = dirname( $plugin_file );
		if ( '.' === $plugin_dir ) {
			$logical_path = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . $plugin_file;
		} else {
			$logical_root = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . str_replace( '/', DIRECTORY_SEPARATOR, $plugin_dir );
			$logical_path = $logical_root . substr( $canonical_path, strlen( $canonical_root ) );
		}

		return realpath( $logical_path ) === $canonical_path ? $logical_path : false;
	}

	private static function is_safe_plugin_file_identifier( $plugin_file ) {
		return is_string( $plugin_file )
			&& '' !== $plugin_file
			&& false === strpos( $plugin_file, "\0" )
			&& '/' !== $plugin_file[0]
			&& false === strpos( $plugin_file, '\\' )
			&& ! preg_match( '#(?:^|/)\.{1,2}(?:/|$)#', $plugin_file );
	}

	public static function resolve_source_path_for_domain( $path, $domain ) {
		$roots = self::get_component_roots_for_domain( $domain );

		if ( 1 !== count( $roots ) ) {
			return false;
		}

		return self::resolve_contained( $path, $roots[0], true );
	}

	private static function get_component_roots_for_domain( $domain ) {
		$map = self::get_domain_component_map();

		if ( ! isset( $map[ $domain ] ) ) {
			return [];
		}

		$roots = [];
		foreach ( $map[ $domain ] as $root ) {
			$canonical_root = realpath( $root );
			if ( false !== $canonical_root ) {
				$roots[ $canonical_root ] = true;
			}
		}

		return array_keys( $roots );
	}

	private static function get_domain_component_map() {
		$map = [];

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		foreach ( (array) get_plugins() as $plugin_file => $plugin_data ) {
			if ( empty( $plugin_data['TextDomain'] ) ) {
				continue;
			}

			$root = self::resolve_registered_plugin_root( $plugin_file );
			if ( false !== $root ) {
				$map[ $plugin_data['TextDomain'] ][] = $root;
			}
		}

		foreach ( (array) wp_get_mu_plugins() as $mu_plugin_file ) {
			if ( ! is_file( $mu_plugin_file ) || ! is_readable( $mu_plugin_file ) ) {
				continue;
			}

			$mu_plugin_data = get_plugin_data( $mu_plugin_file, false, false );
			if ( ! empty( $mu_plugin_data['TextDomain'] )
				&& false !== self::resolve_contained( $mu_plugin_file, WPMU_PLUGIN_DIR, true )
			) {
				$map[ $mu_plugin_data['TextDomain'] ][] = $mu_plugin_file;
			}
		}

		foreach ( (array) wp_get_themes() as $theme ) {
			$text_domain = $theme->get( 'TextDomain' );
			if ( ! $text_domain ) {
				continue;
			}

			if ( false !== self::resolve_contained( $theme->get_stylesheet_directory(), $theme->get_theme_root(), true ) ) {
				$map[ $text_domain ][] = $theme->get_stylesheet_directory();
			}
		}

		return $map;
	}
}
