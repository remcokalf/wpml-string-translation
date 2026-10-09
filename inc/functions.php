<?php

use WPML\ST\Gettext\Hooks;
use WPML\ST\Gettext\HooksFactory;
use function WPML\Container\make;
use WPML\Element\API\Languages;
use WPML\StringTranslation\Infrastructure\TranslateEverything\EnglishSourceLanguage;

add_action( 'plugins_loaded', 'icl_st_init' );

function icl_st_init() {
	global $sitepress_settings, $sitepress, $pagenow, $authordata;

	if ( empty( $sitepress_settings['setup_complete'] ) || ( 'site-new.php' === $pagenow && isset( $_REQUEST['action'] ) && 'add-site' === $_REQUEST['action'] ) ) {
		return;
	}

	add_action( 'icl_update_active_languages', 'icl_st_recompute_string_statuses_on_language_change', 20 );
	add_action( 'update_option_blogname', 'icl_st_update_blogname_actions', 5, 2 );
	add_action( 'update_option_blogdescription', 'icl_st_update_blogdescription_actions', 5, 2 );

	if ( ! isset( $sitepress_settings['existing_content_language_verified'] ) || ! $sitepress_settings['existing_content_language_verified'] ) {
		return;
	}

	if ( ! isset( $sitepress_settings['st']['strings_per_page'] ) ) {
		$sitepress_settings['st']['strings_per_page'] = WPML_ST_DEFAULT_STRINGS_PER_PAGE;
		$sitepress->save_settings( $sitepress_settings );
	}
	if ( is_admin() ) {
		WPML_ST_Strings_Per_Page_Command::register();
	}
	if ( ! isset( $sitepress_settings['st']['icl_st_auto_reg'] ) ) {
		$sitepress_settings['st']['icl_st_auto_reg'] = 'disable';
		$sitepress->save_settings( $sitepress_settings );

		do_action( 'wpml_st_auto_register_default' );
	}
	if ( empty( $sitepress_settings['st']['strings_language'] ) ) {
		$iclsettings['st']['strings_language'] = $sitepress_settings['st']['strings_language'] = EnglishSourceLanguage::resolveForSite();
		$sitepress->save_settings( $iclsettings );
	}

	if ( ! isset( $sitepress_settings['st']['translated-users'] ) ) {
		$sitepress_settings['st']['translated-users'] = array();
	}


	new WPML_PO_Import_Strings_Scripts();
	$po_import_strings = WPML\Container\make( WPML_PO_Import_Strings::class );
	$po_import_strings->maybe_import_po_add_strings();

	$po_export_nonce = isset( $_POST['_wpnonce'] ) ? (string) \WPML\API\Sanitize::string( $_POST['_wpnonce'] ) : '';
	if ( isset( $_POST['icl_st_pie_e'] ) && wp_verify_nonce( $po_export_nonce, 'icl_po_export' ) ) {
		if ( isset( $_GET['status'] ) ) {
			unset( $_GET['status'] );
		}
		$_GET['show_results'] = 'all';
		if ( ! empty( $_POST['icl_st_e_context'] ) ) {
			$_GET['context'] = (string) \WPML\API\Sanitize::string( $_POST['icl_st_e_context'] );
		}

		$_GET['translation_language'] = isset( $_POST['icl_st_e_language'] ) ? (string) \WPML\API\Sanitize::string( $_POST['icl_st_e_language'] ) : '';
		$strings                      = icl_get_string_translations();
		if ( ! empty( $strings ) ) {
			$po = icl_st_generate_po_file( $strings );
		} else {
			$po = '';
		}
		if ( ! isset( $_POST['icl_st_pe_translations'] ) ) {
			$popot  = 'pot';
			$poname = ! empty( $_POST['icl_st_e_context'] ) ? (string) \WPML\API\Sanitize::string( urlencode( $_POST['icl_st_e_context'] ) ) : 'all_context';
		} else {
			$popot  = 'po';
			$poname = \WPML\API\Sanitize::string( $_GET['context'] ?? '' ) . '-' . \WPML\API\Sanitize::string( $_GET['translation_language'] ?? '' );
		}
		header( 'Content-Type: application/force-download' );
		header( 'Content-Type: application/octet-stream' );
		header( 'Content-Type: application/download' );
		header( 'Content-Disposition: attachment; filename=' . $poname . '.' . $popot . ';' );
		header( 'Content-Length: ' . strlen( $po ) );
		echo $po;
		exit( 0 );
	}

	$blog_name_and_desc_hooks = make( WPML_ST_Blog_Name_And_Description_Hooks::class );
	$blog_name_and_desc_hooks->add_hooks();
	add_filter( 'widget_title', 'icl_sw_filters_widget_title', 0 );
	add_filter( 'widget_text', 'icl_sw_filters_widget_text', 0 );

	add_action( 'update_option', 'icl_st_update_widget_title_actions', 5, 3 );

	add_action( 'update_option_widget_text', 'icl_st_update_text_widgets_actions', 5, 2 );
	add_action( 'update_option_sidebars_widgets', 'wpml_st_init_register_widget_titles' );

	if ( isset( $_REQUEST['string-translated'] ) && $_REQUEST['string-translated'] == true ) {
		add_action( 'admin_notices', 'icl_st_admin_notices_string_updated' );
	}

	$user_fields = new WPML_ST_User_Fields( $sitepress, $authordata );
	$user_fields->init_hooks();
}

function wpml_st_init_register_widget_titles() {

	$active_widgets = array();
	$widgets        = (array) get_option( 'sidebars_widgets' );

	foreach ( $widgets as $k => $w ) {
		if ( 'wp_inactive_widgets' != $k && $k != 'array_version' ) {
			if ( is_array( $widgets[ $k ] ) ) {
				foreach ( $widgets[ $k ] as $v ) {
					$active_widgets[] = $v;
				}
			}
		}
	}
	foreach ( $active_widgets as $aw ) {
		$int = preg_match( '#-([0-9]+)$#i', $aw, $matches );
		if ( $int ) {
			$suffix = $matches[1];
		} else {
			$suffix = 1;
		}
		$name = preg_replace( '#-[0-9]+#', '', $aw );

		$value = get_option( 'widget_' . $name, [] );
		if ( isset( $value[ $suffix ]['title'] ) && $value[ $suffix ]['title'] ) {
			$w_title = $value[ $suffix ]['title'];
		} else {
			$w_title                   = wpml_get_default_widget_title( $aw );
			if ( ! isset( $value[ $suffix ] ) || ! is_array( $value[ $suffix ] ) ) {
				$value[ $suffix ] = [];
			}
			$value[ $suffix ]['title'] = $w_title;
			update_option( 'widget_' . $name, $value );
		}

		if ( $w_title ) {
			icl_register_string( WPML_ST_WIDGET_STRING_DOMAIN, 'widget title - ' . md5( $w_title ), $w_title );
		}
	}
}

function wpml_get_default_widget_title( $id ) {
	if ( preg_match( '#archives(-[0-9]+)?$#i', $id ) ) {
		$w_title = 'Archives';
	} elseif ( preg_match( '#categories(-[0-9]+)?$#i', $id ) ) {
		$w_title = 'Categories';
	} elseif ( preg_match( '#calendar(-[0-9]+)?$#i', $id ) ) {
		$w_title = 'Calendar';
	} elseif ( preg_match( '#links(-[0-9]+)?$#i', $id ) ) {
		$w_title = 'Links';
	} elseif ( preg_match( '#meta(-[0-9]+)?$#i', $id ) ) {
		$w_title = 'Meta';
	} elseif ( preg_match( '#pages(-[0-9]+)?$#i', $id ) ) {
		$w_title = 'Pages';
	} elseif ( preg_match( '#recent-posts(-[0-9]+)?$#i', $id ) ) {
		$w_title = 'Recent Posts';
	} elseif ( preg_match( '#recent-comments(-[0-9]+)?$#i', $id ) ) {
		$w_title = 'Recent Comments';
	} elseif ( preg_match( '#rss-links(-[0-9]+)?$#i', $id ) ) {
		$w_title = 'RSS';
	} elseif ( preg_match( '#search(-[0-9]+)?$#i', $id ) ) {
		$w_title = 'Search';
	} elseif ( preg_match( '#tag-cloud(-[0-9]+)?$#i', $id ) ) {
		$w_title = 'Tag Cloud';
	} else {
		$w_title = false;
	}
	return $w_title;
}

function icl_register_string( $context, $name, $value, $allow_empty_value = false, $source_lang = '' ) {
	global $WPML_String_Translation;

	if ( ! $name ) {
		$name = md5( is_array( $value ) ? (string) json_encode( $value ) : $value );
	}

	$strings_language    = $WPML_String_Translation->get_current_string_language( $name );
	$admin_string_filter = $WPML_String_Translation->get_admin_string_filter( $strings_language );

	if ( $admin_string_filter ) {
		$string_id = $admin_string_filter->register_string( $context, $name, $value, $allow_empty_value, $source_lang );
	} else {
		$string_id = null;
	}

	do_action( 'wpml_st_string_registered' );

	return $string_id;
}

add_filter( 'register_string_for_translation', 'icl_register_string', 10, 4 );

function wpml_register_single_string_action( $context, $name, $value, $allow_empty_value = false, $source_lang_code = '' ) {
	icl_register_string( $context, $name, $value, $allow_empty_value, $source_lang_code );
}

add_action( 'wpml_register_single_string', 'wpml_register_single_string_action', 10, 5 );

function icl_translate( $context, $name, $value = false, $allow_empty_value = false, &$has_translation = null, $target_lang = null ) {
	static $translate_wpml_string;

	if ( ! $translate_wpml_string ) {
		$translate_wpml_string = \WPML\Container\make( \WPML\ST\TranslateWpmlString::class );
		$translate_wpml_string->init();
	}

	return $translate_wpml_string->translate( $context, $name, $value, $allow_empty_value, $has_translation, $target_lang );
}

function wpml_st_is_requested_blog() {
	return ! ( is_multisite() && ms_is_switched() )
		   || (int) $GLOBALS['blog_id'] === (int) end( $GLOBALS['_wp_switched_stack'] );
}

function wpml_get_string_current_translation( $value, $context, $name ) {
	$string_id = icl_get_string_id( $value, $context, $name );

	global $WPML_String_Translation;
	$current_lang = $WPML_String_Translation->get_current_string_language( $name );
	$translation  = icl_get_string_by_id( $string_id, $current_lang );

	$value = $translation ? $translation : $value;

	return $value;
}

function icl_st_is_registered_string( $context, $name ) {
	global $wpdb;
	static $cache = array();

	if ( isset( $cache[ $context ][ $name ] ) ) {
		$string_id = $cache[ $context ][ $name ];
	} else {
		$string_id                  = $wpdb->get_var(
			$wpdb->prepare(
				"
          SELECT id
          FROM {$wpdb->prefix}icl_strings
          WHERE context = %s
              AND name = %s
          LIMIT 1",
				$context,
				$name
			)
		);
		$cache[ $context ][ $name ] = $string_id;
	}

	return $string_id;
}

function icl_st_string_has_translations( $context, $name ) {
	global $wpdb;

	return $wpdb->get_var( $wpdb->prepare(
		"
        SELECT COUNT(st.id)
        FROM {$wpdb->prefix}icl_string_translations st
        JOIN {$wpdb->prefix}icl_strings s ON s.id=st.string_id
        WHERE s.context = %s AND s.name = %s",
		$context,
		$name
	) );
}

function icl_update_string_status( $string_id ) {
	global $wpdb;

	$string = new WPML_ST_String( $string_id, $wpdb );

	return $string->update_status();
}

function icl_update_string_status_all() {
	global $wpdb, $sitepress;

	$updater = new WPML_ST_Bulk_Update_Strings_Status( $wpdb, array_keys( $sitepress->get_active_languages() ) );
	$updater->run();
}

function icl_st_recompute_string_statuses_on_language_change() {
	if ( \WPML\ST\BackgroundTask\LoadExistingTranslationsTask::ownsFollowUpStatusRecompute() ) {
		return;
	}

	if ( \WPML\ST\BackgroundTask\UpdateStringsStatusTask::canBeEnqueued() ) {
		\WPML\ST\BackgroundTask\UpdateStringsStatusTask::enqueue();

		return;
	}

	icl_update_string_status_all();
}

function icl_unregister_string( $context, $name ) {
	global $wpdb;

	$string_id = $wpdb->get_var(
		$wpdb->prepare(
			"
				SELECT id FROM {$wpdb->prefix}icl_strings
            	WHERE context=%s AND name=%s
        	",
			$context,
			$name
		)
	);

	if ( $string_id ) {
		do_action( 'wpml_st_before_remove_strings', [ $string_id ] );

		wpml_st_flush_string_cache_for_ids( [ $string_id ] );

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}icl_strings WHERE id=%d", $string_id ) );
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}icl_string_translations WHERE string_id=%d",
				$string_id
			)
		);
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}icl_string_positions WHERE string_id=%d",
				$string_id
			)
		);
	}

	do_action( 'wpml_st_string_unregistered' );

	do_action( 'icl_st_unregister_string', $string_id );
}

function wpml_unregister_string_multi( array $string_ids ) {
	global $wpdb;

	if ( empty( $string_ids ) ) {
		return;
	}

	do_action( 'wpml_st_before_remove_strings', $string_ids );

	wpml_st_flush_string_cache_for_ids( $string_ids );

	$wpdb->query(
		$wpdb->prepare(
			"
		DELETE s.*, t.* FROM {$wpdb->prefix}icl_strings s
		LEFT JOIN {$wpdb->prefix}icl_string_translations t ON s.id = t.string_id
		WHERE s.id IN (" . implode( ', ', array_fill( 0, count( $string_ids ), '%d' ) ) . ')
		',
			$string_ids
		)
	);
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->prefix}icl_string_positions WHERE string_id IN (" . implode( ', ', array_fill( 0, count( $string_ids ), '%d' ) ) . ')',
			$string_ids
		)
	);

	do_action( 'icl_st_unregister_string_multi', $string_ids );

	do_action( 'wpml_st_string_unregistered' );
}

function wpml_st_flush_string_cache_for_ids( array $string_ids ) {
	global $wpdb;

	if ( empty( $string_ids ) ) {
		return;
	}

	$str      = wpml_prepare_in( $string_ids, '%d' );
	$contexts = $wpdb->get_col(
		"SELECT DISTINCT context FROM {$wpdb->prefix}icl_strings WHERE id IN ( {$str} ) AND context != ''"
	);

	foreach ( $contexts as $context ) {
		$group = 'WPML_Register_String_Filter--' . $context;
		$cache = new WPML_WP_Cache( $group );
		$cache->flush_group_cache();
	}

	$keys = $wpdb->get_col(
		"SELECT domain_name_context_md5 FROM {$wpdb->prefix}icl_strings WHERE id IN ( {$str} )"
	);

	foreach ( $keys as $key ) {
		wp_cache_delete( $key, WPML_Register_String_Filter::CACHE_GROUP );
	}
}

function translate_string_filter( $original_value, $context, $name, $has_translation = null, $disable_auto_register = false, $language_code = null ) {
	return icl_t( $context, $name, $original_value, $has_translation, $disable_auto_register, $language_code );
}

add_filter( 'translate_string', 'translate_string_filter', 10, 5 );

function wpml_translate_single_string_filter( $original_value, $context, $name, $language_code = null, $has_translation = null ) {
	$result = $original_value;
	if ( is_string( $name ) ) {
		$result = icl_translate( $context, $name, $original_value, false, $has_translation, $language_code );
	}

	return $result;
}

add_filter( 'wpml_translate_single_string', 'wpml_translate_single_string_filter', 10, 5 );

function icl_t( $context, $name, $original_value = false, &$has_translation = null, $disable_auto_register = false, $language_code = null ) {

	return icl_translate( $context, $name, $original_value, false, $has_translation, $language_code );
}

function is_translated_admin_string( $name ) {
	return WPML_ST_Blog_Name_And_Description_Hooks::is_string( $name );
}

function _icl_is_string_change( $result, $original_value ) {

	if ( $result == false ) {
		return false;
	}

	if ( ! isset( $result['value'] ) ) {
		return false;
	}
	return (
				$result['translated'] && $result['original'] != $original_value ||
				! $result['translated'] && $result['value'] != $original_value
			);
}

function icl_add_string_translation( $string_id, $language, $value = null, $status = false, $translator_id = null, $translation_service = null, $batch_id = null ) {
	global $wpdb;

	$string = new WPML_ST_String( $string_id, $wpdb );

	$translated_string_id = $string->set_translation( $language, $value, $status, $translator_id, $translation_service, $batch_id );

	return $translated_string_id;
}

function wpml_st_set_authorized_object_translation( $string_id, $language, $value, array $provenance ) {
	if ( ! $string_id || ! is_string( $language ) || '' === $language ) {
		return false;
	}

	if ( ! apply_filters( 'wpml_language_is_active', false, $language ) ) {
		return false;
	}

	if ( empty( $provenance['principal'] ) || empty( $provenance['source'] ) ) {
		return false;
	}

	$allowed = apply_filters( 'wpml_st_object_string_translation_authorized', false, $string_id, $language, $provenance );
	if ( true !== $allowed ) {
		return false;
	}

	return icl_add_string_translation( $string_id, $language, $value, ICL_TM_COMPLETE );
}

function wpml_st_set_trusted_string_translation( $string_id, $language, $value, $status = ICL_TM_COMPLETE ) {
	if ( ! $string_id || ! is_string( $language ) || '' === $language ) {
		return false;
	}

	if ( ! apply_filters( 'wpml_language_is_active', false, $language ) ) {
		return false;
	}

	return icl_add_string_translation( $string_id, $language, $value, $status );
}

function icl_update_string_translation(
	$option_name,
	$language,
	$new_value = null,
	$status = false,
	$translator_id = null
) {
	global $WPML_String_Translation;

	return $WPML_String_Translation->get_admin_option( $option_name, $language )
								->update_option(
									'',
									$new_value,
									$status,
									$translator_id,
									0
								);
}

function icl_get_string_id( $string, $context, $name = false ) {
	return WPML\Container\make( 'WPML_ST_String_Factory' )->get_string_id( $string, $context, $name );
}

function icl_get_string_translations() {
	global $sitepress, $wpdb, $wp_query;

	$WPML_ST_Strings = new WPML_ST_Strings( $sitepress, $wpdb, $wp_query );
	return $WPML_ST_Strings->get_string_translations();
}

function icl_get_string_by_id( $string_id, $language_code = false ) {
	global $wpdb, $sitepress_settings;

	$result = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT value, language FROM {$wpdb->prefix}icl_strings WHERE id=%d",
			$string_id
		)
	);

	if ( $result && ( ! $language_code || $language_code === $result->language ) ) {
		return $result->value;
	}

	if ( ! $language_code ) {
		if ( isset( $sitepress_settings['st']['strings_language'] ) ) {
			$language_code = $sitepress_settings['st']['strings_language'];
		} else {
			return false;
		}
	}

	$translations = icl_get_string_translations_by_id( $string_id );
	if ( isset( $translations[ $language_code ] ) ) {
		return $translations[ $language_code ]['value'];
	}

	return false;
}

function icl_get_string_translations_by_id( $string_id ) {
	global $wpdb;

	$found        = false;
	$translations = WPML_Non_Persistent_Cache::get( $string_id, 'icl_get_string_translations_by_id', $found );
	if ( ! $found ) {
		$translations = array();

		if ( $string_id ) {
			$results = $wpdb->get_results( $wpdb->prepare( "SELECT language, value, status FROM {$wpdb->prefix}icl_string_translations WHERE string_id=%d", $string_id ) );
			foreach ( $results as $row ) {
				$translations[ $row->language ] = array(
					'value'  => $row->value,
					'status' => $row->status,
				);
			}
		}
		WPML_Non_Persistent_Cache::set( $string_id, $translations, 'icl_get_string_translations_by_id' );
	}

	return $translations;

}

function icl_get_strings_tracked_in_pages( $string_translations ) {
	global $wpdb;
	$found_strings = $strings_in_page = array();
	foreach ( array_keys( (array) $string_translations ) as $string_id ) {
		$found_strings[] = $string_id;
	}
	if ( $found_strings ) {
		$res = $wpdb->get_results(
			$wpdb->prepare(
				"
	            SELECT kind, string_id  FROM {$wpdb->prefix}icl_string_positions
	            WHERE string_id IN (" . implode( ', ', array_fill( 0, count( $found_strings ), '%d' ) ) . ')',
				array_map( 'intval', $found_strings )
			)
		);
		foreach ( $res as $row ) {
			$strings_in_page[ $row->kind ][ $row->string_id ] = true;
		}
	}
	return $strings_in_page;
}

function icl_sw_filters_widget_title( $val ) {
	$val = icl_translate( WPML_ST_WIDGET_STRING_DOMAIN, 'widget title - ' . md5( $val ), $val );
	return $val;
}

function icl_sw_filters_widget_text( $val ) {
	$val = icl_translate( WPML_ST_WIDGET_STRING_DOMAIN, 'widget body - ' . md5( $val ), $val );
	return $val;
}

function icl_sw_filters_gettext( $translation, $text, $domain, $name = false ) {
	static $gettext_hooks;

	if ( null === $gettext_hooks ) {
		$factory       = WPML\Container\make( HooksFactory::class );
		$gettext_hooks = $factory->create();
		$gettext_hooks = $gettext_hooks ?: false;
	}

	return $gettext_hooks ? $gettext_hooks->gettext_filter( $translation, $text, $domain, $name ) : $translation;
}

function icl_sw_must_track_strings() {
	$gettext_hooks = WPML\Container\make( Hooks::class );

	if ( method_exists( $gettext_hooks, 'must_track_strings' ) ) {
		return $gettext_hooks->must_track_strings();
	}

	return false;
}

function icl_sw_filters_gettext_with_context( $translation, $text, $_gettext_context, $domain ) {
	if ( $_gettext_context ) {
		return icl_sw_filters_gettext(
			$translation,
			$text,
			array(
				'domain'  => $domain,
				'context' => $_gettext_context,
			)
		);
	} else {
		return icl_sw_filters_gettext( $translation, $text, $domain );
	}
}

function icl_sw_filters_ngettext( $translation, $single, $plural, $number, $domain, $_gettext_context ) {
	if ( $number == 1 ) {
		return icl_sw_filters_gettext_with_context( $translation, $single, $_gettext_context, $domain );
	} else {
		return icl_sw_filters_gettext_with_context( $translation, $plural, $_gettext_context, $domain );
	}
}

function icl_sw_filters_nxgettext( $translation, $single, $plural, $number, $_gettext_context, $domain ) {
	return icl_sw_filters_ngettext( $translation, $single, $plural, $number, $domain, $_gettext_context );
}

function icl_st_register_user_strings_all() {
	global $sitepress, $authordata;
	$wpml_translated_users = new WPML_ST_User_Fields( $sitepress, $authordata );
	$processedIds = $wpml_translated_users->init_register_strings();

	do_action( 'wpml_st_string_registered' );

	return $processedIds;
}

function icl_st_update_string_actions( $context, $name, $old_value, $new_value, $force_complete = false ) {
	if ( class_exists( 'WPML_WPDB_User' ) ) {
		global $wpdb;
		require_once dirname( __FILE__ ) . '/wpml-st-string-update.class.php';

		$string_update = new WPML_ST_String_Update( $wpdb );
		$string_update->update_string( $context, $name, $old_value, $new_value, $force_complete );

		do_action( 'wpml_st_string_updated' );
	}
}

function icl_st_update_widget_title_actions( $name, $old, $new ) {
	if ( strpos( $name, 'widget_' ) !== 0 ) {
		return;
	}

	$new = ! is_array( $new ) || array_key_exists( 'title' , $new )
		? [ $new ]
		: $new;
	$old = ! is_array( $old ) || array_key_exists( 'title' , $old )
		? [ $old ]
		: $old;

	$name_prefix = 'widget title - ';

	foreach ( $new as $index => $widget ) {
		if (
			! is_array( $widget ) ||
			! array_key_exists( 'title', $widget )
		) {
			continue;
		}

		if (
			array_key_exists( $index, $old )
			&& is_array( $old[ $index ] )
			&& array_key_exists( 'title', $old[ $index ] )
			&& $old[ $index ]['title']
		) {
			icl_st_update_string_actions(
				WPML_ST_WIDGET_STRING_DOMAIN,
				$name_prefix . md5( $old[ $index ]['title'] ),
				$old[ $index ]['title'],
				$widget['title']
			);
			continue;
		}

		$defaultLang = isset( $defaultLang )
			? $defaultLang
			: Languages::getDefaultCode();

		icl_register_string(
			WPML_ST_WIDGET_STRING_DOMAIN,
			$name_prefix . md5( $new[ $index ]['title'] ),
			$new[ $index ]['title'],
			false,
			$defaultLang
		);
	}
}

function icl_st_update_text_widgets_actions( $old_options, $new_options ) {
	global $wpdb;

	$GLOBALS['__disable_absolute_links_permalink_filter'] = 1;

	$widget_text = get_option( 'widget_text' );
	if ( is_array( $widget_text ) ) {
		$defaultLang = Languages::getDefaultCode();

		foreach ( $widget_text as $k => $w ) {
			if ( isset( $old_options[ $k ]['text'] ) && trim( $old_options[ $k ]['text'] ) && $old_options[ $k ]['text'] != $w['text'] ) {
				$old_md5 = md5( $old_options[ $k ]['text'] );
				$string  = $wpdb->get_row( $wpdb->prepare( "SELECT id, value, status FROM {$wpdb->prefix}icl_strings WHERE context=%s AND name=%s", WPML_ST_WIDGET_STRING_DOMAIN, 'widget body - ' . $old_md5 ) );
				if ( $string ) {
					icl_st_update_string_actions( WPML_ST_WIDGET_STRING_DOMAIN, 'widget body - ' . $old_md5, $old_options[ $k ]['text'], $w['text'] );
				} else {
					icl_register_string( WPML_ST_WIDGET_STRING_DOMAIN, 'widget body - ' . md5( $w['text'] ), $w['text'], false, $defaultLang );
				}
			} elseif ( isset( $new_options[ $k ]['text'] ) && ( ! isset( $old_options[ $k ]['text'] ) || $old_options[ $k ]['text'] != $new_options[ $k ]['text'] ) ) {
				icl_register_string( WPML_ST_WIDGET_STRING_DOMAIN, 'widget body - ' . md5( $new_options[ $k ]['text'] ), $new_options[ $k ]['text'], false, $defaultLang );
			}
		}
	}

	unset( $GLOBALS['__disable_absolute_links_permalink_filter'] );

}

function icl_st_get_contexts( $status ) {
	global $sitepress, $wpdb, $wp_query;

	$wpml_strings = new WPML_ST_Strings( $sitepress, $wpdb, $wp_query );
	return $wpml_strings->get_per_domain_counts( $status );
}

function icl_st_generate_po_file( $strings ) {

	require_once WPML_ST_PATH . '/inc/gettext/wpml-po-parser.class.php';

	$po = WPML_PO_Parser::create_po( $strings );

	return $po;
}

function _icl_st_get_options_writes( $path ) {
	static $found_writes = array();
	if ( is_dir( $path ) ) {
		$dh = opendir( $path );
		if ( ! $dh ) {
			return $found_writes;
		}
		while ( $file = readdir( $dh ) ) {
			if ( $file == '.' || $file == '..' ) {
				continue;
			}
			if ( is_dir( $path . '/' . $file ) ) {
				_icl_st_get_options_writes( $path . '/' . $file );
			} elseif ( preg_match( '#(\.php|\.inc)$#i', $file ) ) {
				$content = (string) file_get_contents( $path . '/' . $file );
				$int     = preg_match_all( '#(add|update)_option\(([^,]+),([^)]+)\)#im', $content, $matches );
				if ( $int ) {
					foreach ( $matches[2] as $m ) {
						$option_name = trim( $m );
						if ( 0 === strpos( $option_name, '"' ) || 0 === strpos( $option_name, "'" ) ) {
							$option_name = trim( $option_name, "\"'" );
						} elseif ( false === strpos( $option_name, '$' ) ) {
							if ( false !== strpos( $option_name, '::' ) ) {
								$cexp = explode( '::', $option_name );
								if ( class_exists( $cexp[0] ) ) {
									if ( defined( $cexp[0] . '::' . $cexp[1] ) ) {
										$option_name = constant( $cexp[0] . '::' . $cexp[1] );
									}
								}
							} else {
								if ( defined( $option_name ) ) {
									$option_name = constant( $option_name );
								}
							}
						} else {
							$option_name = false;
						}
						if ( $option_name ) {
							$found_writes[] = $option_name;
						}
					}
				}
			}
		}
	}
	return $found_writes;
}

if ( ! function_exists( 'array_unique_recursive' ) ) {
	function array_unique_recursive( $array ) {
		$scalars = array();
		foreach ( $array as $key => $value ) {
			if ( is_scalar( $value ) ) {
				if ( isset( $scalars[ $value ] ) ) {
					unset( $array[ $key ] );
				} else {
					$scalars[ $value ] = true;
				}
			} elseif ( is_array( $value ) ) {
				$array[ $key ] = array_unique_recursive( $value );
			}
		}

		return $array;
	}
}

function _icl_st_filter_empty_options_out( $array ) {
	$empty_found = false;
	foreach ( $array as $k => $v ) {
		if ( is_array( $v ) && ! empty( $v ) ) {
			list($array[ $k ], $empty_found) = _icl_st_filter_empty_options_out( $v );
		} else {
			if ( empty( $v ) ) {
				unset( $array[ $k ] );
				$empty_found = true;
			}
		}
	}
	return array( $array, $empty_found );
}

function wpml_register_admin_strings( $serialized_array ) {
	try {
		wpml_st_load_admin_texts()->icl_register_admin_options( unserialize( $serialized_array, [ 'allowed_classes' => false ] ) );
	} catch ( Exception $e ) {
		trigger_error( $e->getMessage(), E_USER_WARNING );
	}
}

function icl_is_string_translation( $translation ) {

	foreach ( $translation as $key => $value ) {
		if ( $key == 'body' or $key == 'title' ) {
			return false;
		}
		if ( preg_match( '/string-(.*)/', $key ) ) {
			return true;
		}
	}

	return false;

}

function icl_st_admin_notices_string_updated() {
	?>
	<div class="updated">
			<p><?php _e( 'Translations for strings updated', 'wpml-string-translation' ); ?></p>
	</div>
	<?php
}

function wpml_st_file_path_is_valid( $path ) {
	return (bool) ( validate_file( $path ) === 0 || validate_file( $path ) === 2 );
}

function wpml_st_extract_context_parameters( $context ) {
	if ( is_array( $context ) ) {
		$domain          = isset( $context['domain'] ) ? $context['domain'] : '';
		$gettext_context = isset( $context['context'] ) ? $context['context'] : '';
	} else {
		$domain          = $context;
		$gettext_context = '';
	}

	return array( $domain, $gettext_context );
}
