<?php

use WPML\Translation\TranslationElements\FieldCompression;

class WPML_Package_TM_Jobs {
	protected $package;

	protected function __construct( $package ) {
		$this->package = $package;
	}

	final public function validate_translations( $create_if_missing = true ) {
		$trid = $this->get_trid( $create_if_missing );

		return $trid != false;
	}

	final protected function get_trid( $create_if_missing = true ) {
		global $sitepress;
		$package   = $this->package;
		$post_trid = $sitepress->get_element_trid( $package->ID, $package->get_translation_element_type() );
		if ( ! $post_trid && $create_if_missing ) {
			$this->set_language_details();
			$post_trid = $sitepress->get_element_trid( $package->ID, $package->get_translation_element_type() );
		}

		return $post_trid;
	}

	final public function set_language_details( $language_code = null ) {
		global $sitepress;
		$package      = $this->package;
		$post_id      = $package->ID;
		$post         = $this->get_translatable_item( $post_id );
		$post_id      = $post->ID;
		$element_type = $package->get_translation_element_type();
		if ( ! $language_code ) {
			$language_code = icl_get_default_language();
		}
		$sitepress->set_element_language_details( $post_id, $element_type, false, $language_code, null, false );
	}

	final public function get_translatable_item( $package ) {
		if ( ! is_object( $package ) || ! is_a( $package, 'WPML_Package' ) ) {
			$package = new WPML_Package( $package );
		}

		return $package;
	}

	final public function delete_translation_jobs() {
		global $wpdb;

		$post_translations = $this->get_post_translations();
		foreach ( $post_translations as $lang => $translation ) {
			$rid         = $wpdb->get_var( $wpdb->prepare( "SELECT rid FROM {$wpdb->prefix}icl_translation_status WHERE translation_id=%d", array( $translation->translation_id ) ) );
			if ( $rid ) {
				$job_id         = $wpdb->get_var( $wpdb->prepare( "SELECT job_id FROM {$wpdb->prefix}icl_translate_job WHERE rid=%d", array( $rid ) ) );

				if ( $job_id ) {
					$wpdb->delete( $wpdb->prefix . 'icl_translate_job', array( 'job_id' => $job_id ) );
					$wpdb->delete( $wpdb->prefix . 'icl_translate', array( 'job_id' => $job_id ) );
				}
			}
		}
	}

	final public function delete_translations() {
		global $sitepress;

		$sitepress->delete_element_translation(
			$this->get_trid(),
			$this->package->get_translation_element_type()
		);
	}

	final public function get_post_translations() {
		global $sitepress;
		$package = $this->package;

		$translation_element_type = $package->get_translation_element_type();
		$trid                     = $this->get_trid();

		return $sitepress->get_element_translations( $trid, $translation_element_type );
	}

	final protected function update_translation_job_needs_update( $job_id ) {
		global $wpdb;
		$update_data  = array( 'translated' => 0 );
		$update_where = array( 'job_id' => $job_id );
		$wpdb->update( "{$wpdb->prefix}icl_translate_job", $update_data, $update_where );
	}

	protected function get_translation_job_id( $rid ) {
		global $wpdb;
		$job_id         = $wpdb->get_var( $wpdb->prepare( "SELECT MAX(job_id) FROM {$wpdb->prefix}icl_translate_job WHERE rid=%d GROUP BY rid", $rid ) );

		return $job_id;
	}

	private function get_translations_job_fields( $job_id ) {
		global $wpdb;
		$translation_fields = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT field_type, field_data, tid, field_translate
				FROM {$wpdb->prefix}icl_translate
				WHERE job_id=%d",
				$job_id
			),
			OBJECT_K
		);

		foreach ( $translation_fields as $field_type => $field ) {
			$translation_fields[ $field_type ]->field_data      = FieldCompression::decompress( $field->field_data );
		}

		return $translation_fields;
	}

	private function delete_translation_field( $tid ) {
		global $wpdb;
		$wpdb->delete( $wpdb->prefix . 'icl_translate', array( 'tid' => $tid ), array( '%d' ) );
	}

	public function get_package() {
		return $this->package;
	}
}
