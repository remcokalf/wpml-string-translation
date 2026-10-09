<?php

require_once WPML_ST_PATH . '/inc/admin-texts/wpml-admin-text-config-sync.class.php';
require_once WPML_ST_PATH . '/inc/admin-texts/wpml-admin-text-string-cleanup.class.php';

function wpml_st_admin_text_config_sync() {
	static $sync;

	if ( ! $sync ) {
		$sync = new WPML_Admin_Text_Config_Sync();
	}

	return $sync;
}

function wpml_st_admin_text_config_parse_started() {
	global $iclTranslationManagement;

	if ( isset( $iclTranslationManagement ) ) {
		$iclTranslationManagement->admin_texts_to_translate = array();
	}

	wpml_st_admin_text_config_sync()->start();
}

function wpml_st_admin_text_config_parse_finished() {
	wpml_st_admin_text_config_sync()->finish();
}

function wpml_st_parse_config( $file_or_object ) {
	global $wpdb;

	require_once WPML_ST_PATH . '/inc/admin-texts/wpml-admin-text-import.class.php';
	$config         = new WPML_Admin_Text_Configuration( $file_or_object );
	$config_array   = $config->get_config_array();
	$config_handler = $file_or_object;

	if ( isset( $file_or_object->type, $file_or_object->admin_text_context ) ) {
		$config_handler = $file_or_object->type . $file_or_object->admin_text_context;
	}

	$st_records          = new WPML_ST_Records( $wpdb );
	$import              = new WPML_Admin_Text_Import( $st_records, new WPML_WP_API() );
	$config_handler_hash = md5( serialize( $config_handler ) );
	$import->parse_config( $config_array, $config_handler_hash );

	wpml_st_admin_text_config_sync()->collect(
		$import->get_configured_option_names(),
		$import->get_configured_translatable_id_names()
	);
}

function wpml_st_cleanup_removed_admin_texts( array $string_ids ) {
	global $wpdb;

	$cleanup = new WPML_Admin_Text_String_Cleanup( $wpdb, wpml_st_admin_text_config_sync() );
	$cleanup->cleanup( $string_ids );
}

add_action( 'wpml_config_parse_started', 'wpml_st_admin_text_config_parse_started' );
add_action( 'wpml_parse_config_file', 'wpml_st_parse_config', 10, 1 );
add_action( 'wpml_parse_custom_config', 'wpml_st_parse_config', 10, 1 );
add_action( 'wpml_config_parse_finished', 'wpml_st_admin_text_config_parse_finished' );
add_action( 'wpml_st_before_remove_strings', 'wpml_st_cleanup_removed_admin_texts', 10, 1 );

function wpml_st_initialize_basic_strings() {
	global $sitepress, $pagenow, $WPML_String_Translation;

	if ( ! class_exists( 'WPML_ST_WP_Loaded_Action' ) ) {
		return;
	}

	$load_action = new WPML_ST_WP_Loaded_Action(
		$sitepress,
		$WPML_String_Translation,
		$pagenow,
		\WPML\SuperGlobals\Request::page()
	);
	if ( $sitepress->is_setup_complete() ) {
		$load_action->run();
	}
}

if ( is_admin() ) {
	add_action( 'wp_loaded', 'wpml_st_initialize_basic_strings' );
}

function icl_st_update_blogname_actions( $old, $new ) {
	icl_st_update_string_actions(
		WPML_ST_Blog_Name_And_Description_Hooks::STRING_DOMAIN,
		WPML_ST_Blog_Name_And_Description_Hooks::STRING_NAME_BLOGNAME,
		$old,
		$new,
		true
	);
}

function icl_st_update_blogdescription_actions( $old, $new ) {
	icl_st_update_string_actions(
		WPML_ST_Blog_Name_And_Description_Hooks::STRING_DOMAIN,
		WPML_ST_Blog_Name_And_Description_Hooks::STRING_NAME_BLOGDESCRIPTION,
		$old,
		$new,
		true
	);
}
