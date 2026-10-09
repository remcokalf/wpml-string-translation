<?php

use WP_CLI\Utils;

define( 'WPINC', 'wp-includes' );

require ABSPATH . WPINC . '/load.php';
require ABSPATH . WPINC . '/default-constants.php';
require ABSPATH . WPINC . '/plugin.php';

global $wp_version, $wp_db_version, $tinymce_version, $required_php_version, $required_mysql_version, $wp_local_package, $upgrading;
require ABSPATH . WPINC . '/version.php';

global $blog_id;

wp_initial_constants();

wp_check_php_mysql_versions();

ini_set( 'magic_quotes_runtime', 0 );
ini_set( 'magic_quotes_sybase', 0 );

date_default_timezone_set( 'UTC' );

wp_fix_server_vars();

if ( apply_filters( 'enable_maintenance_mode', true, $upgrading ) ) {
	wp_maintenance();
}

timer_start();

if ( apply_filters( 'enable_wp_debug_mode_checks', true ) ) {
	wp_debug_mode();
}

if ( WP_CACHE && apply_filters( 'enable_loading_advanced_cache_dropin', true ) ) {
	if ( file_exists( WP_CONTENT_DIR . '/advanced-cache.php' ) ) {
		include WP_CONTENT_DIR . '/advanced-cache.php';
	}
}

wp_set_lang_dir();

require ABSPATH . WPINC . '/compat.php';
require ABSPATH . WPINC . '/functions.php';
require ABSPATH . WPINC . '/class-wp.php';
require ABSPATH . WPINC . '/class-wp-error.php';
require ABSPATH . WPINC . '/pomo/mo.php';

require_wp_db();

global $wpdb;
if ( ! empty( $wpdb->error ) ) {
	wp_die( $wpdb->error );
}

$GLOBALS['table_prefix'] = $table_prefix;
wp_set_wpdb_vars();

wp_start_object_cache();

require ABSPATH . WPINC . '/default-filters.php';

if ( is_multisite() ) {
	Utils\maybe_require( '4.6-alpha-37575', ABSPATH . WPINC . '/class-wp-site-query.php' );
	Utils\maybe_require( '4.6-alpha-37896', ABSPATH . WPINC . '/class-wp-network-query.php' );
	require ABSPATH . WPINC . '/ms-blogs.php';
	require ABSPATH . WPINC . '/ms-settings.php';
} elseif ( ! defined( 'MULTISITE' ) ) {
	define( 'MULTISITE', false );
}

register_shutdown_function( 'shutdown_action_hook' );

if ( SHORTINIT ) {
	return false;
}

require_once ABSPATH . WPINC . '/l10n.php';

apply_filters( 'nocache_headers', [] );

wp_not_installed();

require ABSPATH . WPINC . '/class-wp-walker.php';
require ABSPATH . WPINC . '/class-wp-ajax-response.php';
require ABSPATH . WPINC . '/formatting.php';
require ABSPATH . WPINC . '/capabilities.php';
Utils\maybe_require( '4.4-beta4-35719', ABSPATH . WPINC . '/class-wp-roles.php' );
Utils\maybe_require( '4.4-beta4-35719', ABSPATH . WPINC . '/class-wp-role.php' );
Utils\maybe_require( '4.4-beta4-35719', ABSPATH . WPINC . '/class-wp-user.php' );
require ABSPATH . WPINC . '/query.php';
Utils\maybe_require( '3.7-alpha-25139', ABSPATH . WPINC . '/date.php' );
require ABSPATH . WPINC . '/theme.php';
require ABSPATH . WPINC . '/class-wp-theme.php';
require ABSPATH . WPINC . '/template.php';
require ABSPATH . WPINC . '/user.php';
Utils\maybe_require( '4.4-beta4-35719', ABSPATH . WPINC . '/class-wp-user-query.php' );
Utils\maybe_require( '4.0', ABSPATH . WPINC . '/session.php' );
require ABSPATH . WPINC . '/meta.php';
Utils\maybe_require( '4.4-beta4-35719', ABSPATH . WPINC . '/class-wp-meta-query.php' );
Utils\maybe_require( '4.5-alpha-35776', ABSPATH . WPINC . '/class-wp-metadata-lazyloader.php' );
require ABSPATH . WPINC . '/general-template.php';
require ABSPATH . WPINC . '/link-template.php';
require ABSPATH . WPINC . '/author-template.php';
require ABSPATH . WPINC . '/post.php';
Utils\maybe_require( '4.4-beta4-35719', ABSPATH . WPINC . '/class-walker-page.php' );
Utils\maybe_require( '4.4-beta4-35719', ABSPATH . WPINC . '/class-walker-page-dropdown.php' );
Utils\maybe_require( '4.6-alpha-37890', ABSPATH . WPINC . '/class-wp-post-type.php' );
Utils\maybe_require( '4.4-beta4-35719', ABSPATH . WPINC . '/class-wp-post.php' );
require ABSPATH . WPINC . '/post-template.php';
Utils\maybe_require( '3.6-alpha-23451', ABSPATH . WPINC . '/revision.php' );
Utils\maybe_require( '3.6-alpha-23451', ABSPATH . WPINC . '/post-formats.php' );
require ABSPATH . WPINC . '/post-thumbnail-template.php';
require ABSPATH . WPINC . '/category.php';
Utils\maybe_require( '4.4-beta4-35719', ABSPATH . WPINC . '/class-walker-category.php' );
Utils\maybe_require( '4.4-beta4-35719', ABSPATH . WPINC . '/class-walker-category-dropdown.php' );
require ABSPATH . WPINC . '/category-template.php';
require ABSPATH . WPINC . '/comment.php';
Utils\maybe_require( '4.4-beta4-35719', ABSPATH . WPINC . '/class-wp-comment.php' );
Utils\maybe_require( '4.4-beta4-35719', ABSPATH . WPINC . '/class-wp-comment-query.php' );
Utils\maybe_require( '4.4-beta4-35719', ABSPATH . WPINC . '/class-walker-comment.php' );
require ABSPATH . WPINC . '/comment-template.php';
require ABSPATH . WPINC . '/rewrite.php';
Utils\maybe_require( '4.4-beta4-35719', ABSPATH . WPINC . '/class-wp-rewrite.php' );
require ABSPATH . WPINC . '/feed.php';
require ABSPATH . WPINC . '/bookmark.php';
require ABSPATH . WPINC . '/bookmark-template.php';
require ABSPATH . WPINC . '/kses.php';
require ABSPATH . WPINC . '/cron.php';
require ABSPATH . WPINC . '/deprecated.php';
require ABSPATH . WPINC . '/script-loader.php';
require ABSPATH . WPINC . '/taxonomy.php';
Utils\maybe_require( '4.4-beta4-35719', ABSPATH . WPINC . '/class-wp-term.php' );
Utils\maybe_require( '4.6-alpha-37575', ABSPATH . WPINC . '/class-wp-term-query.php' );
Utils\maybe_require( '4.4-beta4-35719', ABSPATH . WPINC . '/class-wp-tax-query.php' );
require ABSPATH . WPINC . '/update.php';
require ABSPATH . WPINC . '/canonical.php';
require ABSPATH . WPINC . '/shortcodes.php';
Utils\maybe_require( '4.4-beta4-35719', ABSPATH . WPINC . '/embed.php' );
require ABSPATH . WPINC . '/class-wp-embed.php';
require ABSPATH . WPINC . '/media.php';
Utils\maybe_require( '4.4-alpha-34903', ABSPATH . WPINC . '/class-wp-oembed-controller.php' );
require ABSPATH . WPINC . '/http.php';
require_once ABSPATH . WPINC . '/class-http.php';
Utils\maybe_require( '4.4-beta4-35719', ABSPATH . WPINC . '/class-wp-http-streams.php' );
Utils\maybe_require( '4.4-beta4-35719', ABSPATH . WPINC . '/class-wp-http-curl.php' );
Utils\maybe_require( '4.4-beta4-35719', ABSPATH . WPINC . '/class-wp-http-proxy.php' );
Utils\maybe_require( '4.4-beta4-35719', ABSPATH . WPINC . '/class-wp-http-cookie.php' );
Utils\maybe_require( '4.4-beta4-35719', ABSPATH . WPINC . '/class-wp-http-encoding.php' );
Utils\maybe_require( '4.4-beta4-35719', ABSPATH . WPINC . '/class-wp-http-response.php' );
Utils\maybe_require( '4.6-alpha-37438', ABSPATH . WPINC . '/class-wp-http-requests-response.php' );
require ABSPATH . WPINC . '/widgets.php';
Utils\maybe_require( '4.4-beta4-35719', ABSPATH . WPINC . '/class-wp-widget.php' );
Utils\maybe_require( '4.4-beta4-35719', ABSPATH . WPINC . '/class-wp-widget-factory.php' );
require ABSPATH . WPINC . '/nav-menu.php';
require ABSPATH . WPINC . '/nav-menu-template.php';
require ABSPATH . WPINC . '/admin-bar.php';
Utils\maybe_require( '4.4-alpha-34928', ABSPATH . WPINC . '/rest-api.php' );
Utils\maybe_require( '4.4-beta4-35719', ABSPATH . WPINC . '/rest-api/class-wp-rest-server.php' );
Utils\maybe_require( '4.4-beta4-35719', ABSPATH . WPINC . '/rest-api/class-wp-rest-response.php' );
Utils\maybe_require( '4.4-beta4-35719', ABSPATH . WPINC . '/rest-api/class-wp-rest-request.php' );

if ( is_multisite() ) {
	require ABSPATH . WPINC . '/ms-functions.php';
	require ABSPATH . WPINC . '/ms-default-filters.php';
	require ABSPATH . WPINC . '/ms-deprecated.php';
}

wp_plugin_directory_constants();

$symlinked_plugins_supported = function_exists( 'wp_register_plugin_realpath' );
if ( $symlinked_plugins_supported ) {
	$GLOBALS['wp_plugin_paths'] = [];
}

foreach ( wp_get_mu_plugins() as $mu_plugin ) {
	include_once $mu_plugin;
}
unset( $mu_plugin );

if ( is_multisite() ) {
	foreach ( wp_get_active_network_plugins() as $network_plugin ) {
		if ( $symlinked_plugins_supported ) {
			wp_register_plugin_realpath( $network_plugin );
		}
		include_once $network_plugin;
	}
	unset( $network_plugin );
}

do_action( 'muplugins_loaded' );

if ( is_multisite() ) {
	ms_cookie_constants();
}

wp_cookie_constants();

wp_ssl_constants();

require ABSPATH . WPINC . '/vars.php';

create_initial_taxonomies();
create_initial_post_types();

register_theme_directory( get_theme_root() );

foreach ( wp_get_active_and_valid_plugins() as $plugin ) {
	if ( $symlinked_plugins_supported ) {
		wp_register_plugin_realpath( $plugin );
	}
	include_once $plugin;
}
unset( $plugin, $symlinked_plugins_supported );

require ABSPATH . WPINC . '/pluggable.php';
require ABSPATH . WPINC . '/pluggable-deprecated.php';

wp_set_internal_encoding();

if ( WP_CACHE && function_exists( 'wp_cache_postload' ) ) {
	wp_cache_postload();
}

do_action( 'plugins_loaded' );

wp_functionality_constants();

if ( ! function_exists( 'get_magic_quotes_gpc' ) ) {
	function get_magic_quotes_gpc() {
		return false;
	}
}

wp_magic_quotes();

do_action( 'sanitize_comment_cookies' );

$GLOBALS['wp_the_query'] = new WP_Query();

$GLOBALS['wp_query'] = $GLOBALS['wp_the_query'];

$GLOBALS['wp_rewrite'] = new WP_Rewrite();

$GLOBALS['wp'] = new WP();

$GLOBALS['wp_widget_factory'] = new WP_Widget_Factory();

$GLOBALS['wp_roles'] = new WP_Roles();

do_action( 'setup_theme' );

wp_templating_constants();

load_default_textdomain();

$locale      = get_locale();
$locale_file = WP_LANG_DIR . "/$locale.php";
if ( ( 0 === validate_file( $locale ) ) && is_readable( $locale_file ) ) {
	require $locale_file;
}
unset( $locale_file );

require_once ABSPATH . WPINC . '/locale.php';

$GLOBALS['wp_locale'] = new WP_Locale();

global $pagenow;
if ( ! defined( 'WP_INSTALLING' ) || 'wp-activate.php' === $pagenow ) {
	if ( TEMPLATEPATH !== STYLESHEETPATH && file_exists( STYLESHEETPATH . '/functions.php' ) ) {
		include STYLESHEETPATH . '/functions.php';
	}
	if ( file_exists( TEMPLATEPATH . '/functions.php' ) ) {
		include TEMPLATEPATH . '/functions.php';
	}
}

do_action( 'after_setup_theme' );

$GLOBALS['wp']->init();

do_action( 'init' );

if ( is_multisite() && ! defined( 'WP_INSTALLING' ) ) {
	$file = ms_site_check();
	if ( true !== $file ) {
		require $file;
		die();
	}
	unset( $file );
}

do_action( 'wp_loaded' );
