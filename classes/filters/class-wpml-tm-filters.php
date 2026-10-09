<?php

class WPML_TM_Filters {
	private $string_lang_codes;

	private $wpdb;

	private $sitepress;

	public function __construct( wpdb $wpdb, SitePress $sitepress ) {
		$this->wpdb      = $wpdb;
		$this->sitepress = $sitepress;
	}

	public function filter_tm_source_langs( WPML_Language_Collection $source_langs ) {
		foreach ( $this->get_string_lang_codes() as $lang_code ) {
			$source_langs->add( $lang_code );
		}

		return $source_langs;
	}

	private function get_string_lang_codes() {
		$wpdb = $this->wpdb;

		if ( null === $this->string_lang_codes ) {
			$this->string_lang_codes = $this->wpdb->get_col( "SELECT DISTINCT(s.language) FROM {$wpdb->prefix}icl_strings s" );
		}

		return $this->string_lang_codes;
	}

	public function job_assigned_to_filter( $assigned_correctly, $string_translation_id, $translator_id, $service ) {
		if ( ( ! $service || $service === 'local' ) && strpos( (string) $string_translation_id, 'string|' ) !== false ) {
			$string_translation_id = preg_replace( '/[^0-9]/', '', (string) $string_translation_id );
			$this->wpdb->update(
				$this->wpdb->prefix . 'icl_string_translations',
				array( 'translator_id' => $translator_id ),
				array( 'id' => $string_translation_id )
			);
			$assigned_correctly = true;
		}

		return $assigned_correctly;
	}
}
