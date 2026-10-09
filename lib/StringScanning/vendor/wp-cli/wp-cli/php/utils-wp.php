<?php


namespace WP_CLI\Utils;

use ReflectionClass;
use ReflectionParameter;
use WP_CLI;
use WP_CLI\UpgraderSkin;

function wp_not_installed() {
	global $wpdb, $table_prefix;
	if ( ! is_blog_installed() && ! defined( 'WP_INSTALLING' ) ) {
		$tables         = $wpdb->get_col( "SHOW TABLES LIKE '%_options'" );
		$found_prefixes = [];
		if ( count( $tables ) ) {
			foreach ( $tables as $table ) {
				$maybe_prefix = substr( $table, 0, - strlen( 'options' ) );
				if ( $maybe_prefix !== $table_prefix ) {
					$found_prefixes[] = $maybe_prefix;
				}
			}
		}
		if ( count( $found_prefixes ) ) {
			sort( $found_prefixes );
			$prefix_list   = implode( ', ', $found_prefixes );
			$install_label = count( $found_prefixes ) > 1 ? 'installations' : 'installation';
			WP_CLI::error(
				"The site you have requested is not installed.\n" .
				"Your table prefix is '{$table_prefix}'. Found {$install_label} with table prefix: {$prefix_list}.\n" .
				'Or, run `wp core install` to create database tables.'
			);
		} else {
			WP_CLI::error(
				"The site you have requested is not installed.\n" .
				'Run `wp core install` to create database tables.'
			);
		}
	}
}


function wp_debug_mode() {
	if ( WP_CLI::get_config( 'debug' ) ) {
		if ( ! defined( 'WP_DEBUG' ) ) {
			define( 'WP_DEBUG', true );
		}

		error_reporting( E_ALL & ~E_DEPRECATED );
	} else {
		if ( WP_DEBUG ) {
			error_reporting( E_ALL );

			if ( WP_DEBUG_DISPLAY ) {
				ini_set( 'display_errors', 1 );
			} elseif ( null !== WP_DEBUG_DISPLAY ) {
				ini_set( 'display_errors', 0 );
			}

			if ( in_array( strtolower( (string) WP_DEBUG_LOG ), [ 'true', '1' ], true ) ) {
				$log_path = WP_CONTENT_DIR . '/debug.log';
			} elseif ( is_string( WP_DEBUG_LOG ) ) {
				$log_path = WP_DEBUG_LOG;
			} else {
				$log_path = false;
			}

			if ( false !== $log_path ) {
				ini_set( 'log_errors', 1 );
				ini_set( 'error_log', $log_path );
			}
		} else {
			error_reporting( E_CORE_ERROR | E_CORE_WARNING | E_COMPILE_ERROR | E_ERROR | E_WARNING | E_PARSE | E_USER_ERROR | E_USER_WARNING | E_RECOVERABLE_ERROR );
		}

		if ( defined( 'XMLRPC_REQUEST' ) || defined( 'REST_REQUEST' ) || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
			ini_set( 'display_errors', 0 );
		}
	}

	ini_set( 'display_errors', function_exists( 'xdebug_debug_zval' ) ? false : 'stderr' );
}

function replace_wp_die_handler() {
	\remove_filter( 'wp_die_handler', '_default_wp_die_handler' );
	\add_filter(
		'wp_die_handler',
		function () {
			return __NAMESPACE__ . '\\wp_die_handler';
		}
	);
}

function wp_die_handler( $message ) {

	if ( $message instanceof \WP_Error ) {
		$text_message = $message->get_error_message();
		$error_data   = $message->get_error_data( 'internal_server_error' );
		if ( ! empty( $error_data['error']['file'] )
			&& false !== stripos( $error_data['error']['file'], 'themes/functions.php' ) ) {
			$text_message = 'An unexpected functions.php file in the themes directory may have caused this internal server error.';
		}
	} else {
		$text_message = $message;
	}

	$text_message = wp_clean_error_message( $text_message );

	WP_CLI::error( $text_message );
}

function wp_clean_error_message( $message ) {
	$original_message = trim( $message );
	$message          = $original_message;
	if ( preg_match( '|^\<h1>(.+?)</h1>|', $original_message, $matches ) ) {
		$message = $matches[1] . '.';
	}
	if ( preg_match( '|\<p>(.+?)</p>|', $original_message, $matches ) ) {
		$message .= ' ' . $matches[1];
	}

	$search_replace = [
		'<code>'  => '`',
		'</code>' => '`',
	];
	$message        = str_replace( array_keys( $search_replace ), array_values( $search_replace ), $message );
	$message        = namespace\strip_tags( $message );
	$message        = html_entity_decode( $message, ENT_COMPAT, 'UTF-8' );

	return $message;
}

function wp_redirect_handler( $url ) {
	WP_CLI::warning( 'Some code is trying to do a URL redirect. Backtrace:' );

	ob_start();
	debug_print_backtrace();
	fwrite( STDERR, ob_get_clean() );

	return $url;
}

function maybe_require( $since, $path ) {
	if ( wp_version_compare( $since, '>=' ) ) {
		require $path;
	}
}

function get_upgrader( $class_name, $insecure = false ) {
	if ( ! class_exists( '\WP_Upgrader' ) ) {
		if ( file_exists( ABSPATH . 'wp-admin/includes/class-wp-upgrader.php' ) ) {
			include ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		}
	}

	if ( ! class_exists( '\WP_Upgrader_Skin' ) ) {
		if ( file_exists( ABSPATH . 'wp-admin/includes/class-wp-upgrader-skin.php' ) ) {
			include ABSPATH . 'wp-admin/includes/class-wp-upgrader-skin.php';
		}
	}

	$uses_insecure_flag = false;

	$reflection  = new ReflectionClass( $class_name );
	$constructor = $reflection->getConstructor();
	if ( $constructor ) {
		$arguments = $constructor->getParameters();
		foreach ( $arguments as $argument ) {
			if ( 'insecure' === $argument->name ) {
				$uses_insecure_flag = true;
				break;
			}
		}
	}

	if ( $uses_insecure_flag ) {
		return new $class_name( new UpgraderSkin(), $insecure );
	} else {
		return new $class_name( new UpgraderSkin() );
	}
}

function get_plugin_name( $basename ) {
	if ( false === strpos( $basename, '/' ) ) {
		$name = basename( $basename, '.php' );
	} else {
		$name = dirname( $basename );
	}

	return $name;
}

function is_plugin_skipped( $file ) {
	$name = get_plugin_name( str_replace( WP_PLUGIN_DIR . '/', '', $file ) );

	$skipped_plugins = WP_CLI::get_runner()->config['skip-plugins'];
	if ( true === $skipped_plugins ) {
		return true;
	}

	if ( ! is_array( $skipped_plugins ) ) {
		$skipped_plugins = explode( ',', $skipped_plugins );
	}

	return in_array( $name, array_filter( $skipped_plugins ), true );
}

function get_theme_name( $path ) {
	return basename( $path );
}

function is_theme_skipped( $path ) {
	$name = get_theme_name( $path );

	$skipped_themes = WP_CLI::get_runner()->config['skip-themes'];
	if ( true === $skipped_themes ) {
		return true;
	}

	if ( ! is_array( $skipped_themes ) ) {
		$skipped_themes = explode( ',', $skipped_themes );
	}

	return in_array( $name, array_filter( $skipped_themes ), true );
}

function wp_register_unused_sidebar() {

	register_sidebar(
		[
			'name'          => __( 'Inactive Widgets' ),
			'id'            => 'wp_inactive_widgets',
			'class'         => 'inactive-sidebar',
			'description'   => __( 'Drag widgets here to remove them from the sidebar but keep their settings.' ),
			'before_widget' => '',
			'after_widget'  => '',
			'before_title'  => '',
			'after_title'   => '',
		]
	);
}

function wp_get_cache_type() {
	global $_wp_using_ext_object_cache, $wp_object_cache;

	$message = 'Unknown';

	if ( ! empty( $_wp_using_ext_object_cache ) ) {
		if ( isset( $wp_object_cache->m ) && $wp_object_cache->m instanceof \Memcached ) {
			$message = 'Memcached';

		} elseif ( isset( $wp_object_cache->mc ) ) {
			$is_memcache = true;
			foreach ( $wp_object_cache->mc as $bucket ) {
				if ( ! $bucket instanceof \Memcache && ! $bucket instanceof \Memcached ) {
					$is_memcache = false;
				}
			}

			if ( $is_memcache ) {
				$message = 'Memcache';
			}

		} elseif ( $wp_object_cache instanceof \XCache_Object_Cache ) {
			$message = 'Xcache';

		} elseif ( class_exists( 'WinCache_Object_Cache' ) ) {
			$message = 'WinCache';

		} elseif ( class_exists( 'APC_Object_Cache' ) ) {
			$message = 'APC';

		} elseif ( isset( $wp_object_cache->redis ) && $wp_object_cache->redis instanceof \Redis ) {
			$message = 'Redis';

		} elseif ( method_exists( $wp_object_cache, 'redis_instance' ) && method_exists( $wp_object_cache, 'redis_status' ) ) {
			$message = 'Redis';

		} elseif ( method_exists( $wp_object_cache, 'config' ) && method_exists( $wp_object_cache, 'connection' ) ) {
			$message = 'Redis';

		} elseif ( isset( $wp_object_cache->lcache ) && $wp_object_cache->lcache instanceof \LCache\Integrated ) {
			$message = 'WP LCache';

		} elseif ( function_exists( 'w3_instance' ) ) {
			$config = w3_instance( 'W3_Config' );

			if ( $config->get_boolean( 'objectcache.enabled' ) ) {
				$message = 'W3TC ' . $config->get_string( 'objectcache.engine' );
			}
		}
	} else {
		$message = 'Default';
	}

	return $message;
}

function wp_clear_object_cache() {
	global $wpdb, $wp_object_cache;

	$wpdb->queries = [];

	if ( function_exists( 'wp_cache_flush_runtime' ) && function_exists( 'wp_cache_supports' ) ) {
		if ( wp_cache_supports( 'flush_runtime' ) ) {
			wp_cache_flush_runtime();
			return;
		}
	}

	if ( ! is_object( $wp_object_cache ) ) {
		return;
	}

	if ( isset( $wp_object_cache->group_ops ) ) {
		$wp_object_cache->group_ops = [];
	}
	if ( isset( $wp_object_cache->stats ) ) {
		$wp_object_cache->stats = [];
	}
	if ( isset( $wp_object_cache->memcache_debug ) ) {
		$wp_object_cache->memcache_debug = [];
	}
	if ( isset( $wp_object_cache->cache ) ) {
		$wp_object_cache->cache = [];
	}
}

function wp_get_table_names( $args, $assoc_args = [] ) {
	global $wpdb;

	if ( get_flag_value( $assoc_args, 'base-tables-only' ) && get_flag_value( $assoc_args, 'views-only' ) ) {
		WP_CLI::error( 'You cannot supply --base-tables-only and --views-only at the same time.' );
	}

	if ( get_flag_value( $assoc_args, 'base-tables-only' ) ) {
		$tables_sql = 'SHOW FULL TABLES WHERE Table_Type = "BASE TABLE"';

	} elseif ( get_flag_value( $assoc_args, 'views-only' ) ) {
		$tables_sql = 'SHOW FULL TABLES WHERE Table_Type = "VIEW"';

	}

	if ( get_flag_value( $assoc_args, 'all-tables' ) ) {
		if ( empty( $tables_sql ) ) {
			$tables_sql = 'SHOW TABLES';
		}

		$tables = $wpdb->get_col( $tables_sql, 0 );

	} elseif ( get_flag_value( $assoc_args, 'all-tables-with-prefix' ) ) {
		if ( empty( $tables_sql ) ) {
			$tables_sql = $wpdb->prepare( 'SHOW TABLES LIKE %s', esc_like( $wpdb->get_blog_prefix() ) . '%' );
		} else {
			$tables_sql .= sprintf( " AND %s LIKE '%s'", esc_sql_ident( 'Tables_in_' . $wpdb->dbname ), esc_like( $wpdb->get_blog_prefix() ) . '%' );
		}

		$tables = $wpdb->get_col( $tables_sql, 0 );

	} else {
		$scope = get_flag_value( $assoc_args, 'scope', 'all' );

		if ( get_flag_value( $assoc_args, 'network' ) && is_multisite() ) {
			$network_global_scope = in_array( $scope, [ 'all', 'global', 'ms_global' ], true ) ? ( 'all' === $scope ? 'global' : $scope ) : '';
			$wp_tables            = array_values( $wpdb->tables( $network_global_scope ) );
			if ( in_array( $scope, [ 'all', 'blog' ], true ) ) {
				$blog_ids = $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs WHERE site_id = $wpdb->siteid" );
				foreach ( $blog_ids as $blog_id ) {
					$wp_tables = array_merge( $wp_tables, array_values( $wpdb->tables( 'blog', true  , $blog_id ) ) );
				}
			}
		} else {
			$wp_tables = array_values( $wpdb->tables( $scope ) );
		}

		if ( wp_version_compare( '6.1', '>=' ) || ! global_terms_enabled() ) {
			$wp_tables = array_values( array_diff( $wp_tables, [ $wpdb->sitecategories ] ) );
		}

		$tables = $wpdb->get_col( sprintf( "SHOW TABLES WHERE %s IN ('%s')", esc_sql_ident( 'Tables_in_' . $wpdb->dbname ), implode( "', '", $wpdb->_escape( $wp_tables ) ) ) );

		if ( get_flag_value( $assoc_args, 'base-tables-only' ) || get_flag_value( $assoc_args, 'views-only' ) ) {
			$views_query_tables = $wpdb->get_col( $tables_sql, 0 );
			$tables             = array_intersect( $tables, $views_query_tables );
		}
	}

	if ( $args ) {
		$args_tables = [];
		foreach ( $args as $arg ) {
			if ( false !== strpos( $arg, '*' ) || false !== strpos( $arg, '?' ) ) {
				$args_tables = array_merge(
					$args_tables,
					array_filter(
						$tables,
						function ( $v ) use ( $arg ) {
							return fnmatch( $arg, $v );
						}
					)
				);
			} else {
				$args_tables[] = $arg;
			}
		}
		$args_tables = array_values( array_unique( $args_tables ) );
		$tables      = array_values( array_intersect( $tables, $args_tables ) );
		if ( empty( $tables ) ) {
			WP_CLI::error( sprintf( "Couldn't find any tables matching: %s", implode( ' ', $args ) ) );
		}
	}

	return $tables;
}

function strip_tags( $string ) {
	if ( function_exists( 'wp_strip_all_tags' ) ) {
		return \wp_strip_all_tags( $string );
	}

	$string = preg_replace(
		'@<(script|style)[^>]*?>.*?</\\1>@si',
		'',
		$string
	);

	$string = \strip_tags( $string );

	return trim( $string );
}
