<?php
/**
 * Plugin Name: WPML String Translation
 * Plugin URI: https://wpml.org/documentation/wpml-core-and-add-on-plugins/string-translation/
 * Description: Adds theme and plugins localization capabilities to WPML | <a href="https://wpml.org/documentation/translating-your-contents/strings/">Documentation</a> | <a href="https://wpml.org/version/wpml-string-translation-5-1-0/">WPML String Translation 5.1.0 release notes</a>
 * Author: OnTheGoSystems
 * Author URI: http://www.onthegosystems.com/
 * Version: 5.1.0
 * Plugin Slug: wpml-string-translation
 *
 * @package WPML\ST
 */

if ( defined( 'WPML_ST_VERSION' ) || get_option( '_wpml_inactive' ) ) {
	return;
}

if ( ! defined( 'WPML_ST_PATH' ) ) {
	define( 'WPML_ST_PATH', dirname( __FILE__ ) );
}

add_action( 'admin_init', 'wpml_st_verify_wpml' );

if ( ! defined( 'ICL_SITEPRESS_VERSION' ) ) {
	return;
}

if ( ! WPML_Core_Version_Check::is_ok( dirname( __FILE__ ) . '/wpml-dependencies.json' ) ) {
	return;
}


define( 'WPML_ST_VERSION', '5.1.0' );


require WPML_ST_PATH . '/inc/functions-load.php';
require WPML_ST_PATH . '/inc/constants.php';

require_once WPML_ST_PATH . '/classes/class-wpml-st-initialize.php';
$config             = require_once WPML_ST_PATH . '/StringTranslation/config.php';
$wpml_st_initialize = new WPML_ST_Initialize( $config );
$wpml_st_initialize->load();

function wpml_st_verify_wpml() {
	if ( ! class_exists( 'WPML_ST_Verify_Dependencies' ) ) {
		require_once WPML_ST_PATH . '/classes/class-wpml-st-verify-dependencies.php';
	}

	$verifier     = new WPML_ST_Verify_Dependencies();
	$wpml_version = defined( 'ICL_SITEPRESS_VERSION' ) ? ICL_SITEPRESS_VERSION : false;
	$verifier->verify_wpml( $wpml_version );
}

function wpml_st_core_loaded() {
	global $wpdb;

	new WPML_ST_TM_Jobs( $wpdb );

	$action_filter_loader = new WPML_Action_Filter_Loader();
	$action_filter_loader->load( WPML\ST\Actions::get() );

	\WPML\ST\Batch\Translation\Module::init();
}

function load_wpml_st_basics() {
	if ( ! WPML_Core_Version_Check::is_ok( dirname( __FILE__ ) . '/wpml-dependencies.json' ) ) {
		return;
	}

	global $WPML_String_Translation, $sitepress;

	$WPML_String_Translation = WPML\Container\make( WPML_String_Translation::class );
	$WPML_String_Translation->set_basic_hooks();

	require WPML_ST_PATH . '/inc/package-translation/wpml-package-translation.php';

	\WPML\ST\PackageTranslation\StringRowsCache::register();

	add_action( 'wpml_loaded', 'wpml_st_core_loaded', 10 );

	if ( $sitepress->is_setup_complete() ) {
		$mo_scan_factory = new WPML_ST_Translations_File_Scan_Factory();

		if ( $mo_scan_factory->check_core_dependencies() ) {
			$mo_scan_hooks = $mo_scan_factory->create_hooks();
			foreach ( $mo_scan_hooks as $mo_scan_hook ) {
				$mo_scan_hook->add_hooks();
			}
		}
	}

}

add_action( 'wpml_before_init', 'load_wpml_st_basics' );
