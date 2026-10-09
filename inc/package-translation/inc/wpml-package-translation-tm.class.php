<?php

class WPML_Package_TM extends WPML_Package_TM_Jobs {
	public function __construct( $package ) {
		parent::__construct( $package );
	}

	public function get_translation_statuses() {
		global $sitepress;
		$package = $this->package;

		$post_trid = $this->get_trid();
		$items     = array();
		if ( $post_trid ) {
			$translation_element_type = $package->get_translation_element_type();
			$post_translations        = $sitepress->get_element_translations( $post_trid, $translation_element_type );
			foreach ( $post_translations as $lang => $translation ) {
				$translation->trid = $post_trid;
				$item[]            = $this->set_translation_status( $package, $translation, $lang );
			}
		} else {
			$items[] = $package;
		}

		return $items;
	}

	private function set_translation_status( $package, $translation, $lang ) {
		global $wpdb;

		if ( ! $package->trid ) {
			$package->trid = $translation->trid;
		}

		$res = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT status, needs_update, md5 FROM {$wpdb->prefix}icl_translation_status WHERE translation_id = %d",
				$translation->translation_id
			)
		);
		$_suffix     = str_replace( '-', '_', $lang );
		$status      = ICL_TM_NOT_TRANSLATED;
		if ( $res ) {
			$status          = $res->status;
			$index           = 'needs_update_' . $_suffix;
			$package->$index = $res->needs_update;
		}
		$index           = 'status_' . $_suffix;
		$package->$index = apply_filters( 'wpml_translation_status', $status, $translation->trid, $lang, 'package' );

		return $package;
	}

	public function is_translation_in_progress() {
		global $wpdb;

		$post_translations = $this->get_post_translations();

		foreach ( $post_translations as $lang => $translation ) {
			$res = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT status, needs_update, md5
					 FROM {$wpdb->prefix}icl_translation_status
					 WHERE translation_id = %d",
					$translation->translation_id
				)
			);
			if ( $res && $res->status == ICL_TM_IN_PROGRESS ) {
				return true;
			}
		}

		return false;
	}

	private function get_item_md5_translations( $trid ) {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t.translation_id, s.md5
				 FROM {$wpdb->prefix}icl_translations t
				 NATURAL JOIN {$wpdb->prefix}icl_translation_status s
				 WHERE t.trid = %d
					AND t.source_language_code IS NOT NULL",
				$trid
			)
		);
	}

	private function get_translation_state( $translation ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT status, translator_id, needs_update, md5, translation_service, translation_package, timestamp, links_fixed
				 FROM {$wpdb->prefix}icl_translation_status
				 WHERE translation_id = %d",
				$translation->translation_id
			),
			ARRAY_A
		);
	}

}
