<?php

use function WPML\Container\make;

function filter_tm_source_langs( $source_languages ) {
	global $wpdb, $sitepress;

	static $tm_filter;
	if ( ! $tm_filter ) {
		$tm_filter = new WPML_TM_Filters( $wpdb, $sitepress );
	}

	return $tm_filter->filter_tm_source_langs( $source_languages );
}

function wpml_st_filter_job_assignment( $assigned_correctly, $string_translation_id, $translator_id, $service ) {
	global $wpdb, $sitepress;

	$tm_filter = new WPML_TM_Filters( $wpdb, $sitepress );

	return $tm_filter->job_assigned_to_filter( $assigned_correctly, $string_translation_id, $translator_id, $service );
}

add_filter( 'wpml_tm_allowed_source_languages', 'filter_tm_source_langs', 10, 1 );
add_filter( 'wpml_job_assigned_to_after_assignment', 'wpml_st_filter_job_assignment', 10, 4 );

function wpml_st_blog_title_filter( $val ) {
	$filter = make( WPML_ST_Blog_Name_And_Description_Hooks::class );
	return $filter->option_blogname_filter( $val );
}

function wpml_st_blog_description_filter( $val ) {
	$filter = make( WPML_ST_Blog_Name_And_Description_Hooks::class );
	return $filter->option_blogdescription_filter( $val );
}
