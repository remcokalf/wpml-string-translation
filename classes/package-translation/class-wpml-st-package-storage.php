<?php
use WPML\ST\StringValue;

use WPML\ST\PackageTranslation\StringRowsCache;

class WPML_ST_Package_Storage {

	private $package_id;

	private $wpdb;

	public function __construct( $package_id, wpdb $wpdb ) {
		$this->package_id = $package_id;
		$this->wpdb       = $wpdb;
	}

	public function update( $string_title, $string_type, $string_value, $string_id ) {

		$update_where = array( 'id' => $string_id );

		$row = StringRowsCache::getRow( $string_id );
		if ( null === $row ) {
			$row = $this->read_row( $string_id );
		}

		$type_or_title_updated = 0;
		$update_data           = array(
			'type'  => $string_type,
			'title' => $this->truncate_long_string( $string_title ),
		);
		if ( $this->fields_differ( $update_data, $row ) ) {
			$type_or_title_updated = $this->wpdb->update( $this->wpdb->prefix . 'icl_strings', $update_data, $update_where );
			StringRowsCache::noteFieldUpdate( $string_id, $update_data );
		}

		$package_id_or_value_updated = 0;
		$update_data                 = array(
			'string_package_id' => $this->package_id,
			'value'             => $string_value,
		);

		if ( StringValue::isReady() ) {
			$update_data['has_text'] = StringValue::hasTextFlag( $string_value );
		}

		if ( $this->fields_differ( $update_data, $row ) ) {
			$package_id_or_value_updated = $this->wpdb->update( $this->wpdb->prefix . 'icl_strings', $update_data, $update_where );
			StringRowsCache::noteFieldUpdate( $string_id, $update_data );
		}

		if ( $package_id_or_value_updated ) {
			if ( StringRowsCache::isBatching() ) {
				if ( StringRowsCache::isNewlyInserted( $string_id ) ) {
					StringRowsCache::bufferMark( $this->package_id );
				} else {
					StringRowsCache::bufferMark( $this->package_id, $string_id );
				}
			} else {
				$this->set_string_status_to_needs_update_if_translated( $string_id );

				$this->set_translations_to_needs_update();
			}
		}

		return $type_or_title_updated || $package_id_or_value_updated;
	}

	public function flush_batched_needs_update_marks( array $string_ids ) {
		if ( ! empty( $string_ids ) ) {
			$in = wpml_prepare_in( $string_ids, '%d' );

			$this->wpdb->query(
				"UPDATE {$this->wpdb->prefix}icl_strings
					SET status=" . ICL_TM_NEEDS_UPDATE . "
					WHERE id IN ( $in ) AND status<>" . ICL_TM_NOT_TRANSLATED
			);
			$this->wpdb->query(
				"UPDATE {$this->wpdb->prefix}icl_string_translations
					SET status=" . ICL_TM_NEEDS_UPDATE . "
					WHERE string_id IN ( $in ) AND status<>" . ICL_TM_NOT_TRANSLATED
			);
		}

		$this->set_translations_to_needs_update();
	}

	private function read_row( $string_id ) {
		$row = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT id, value, string_package_id, type, title FROM {$this->wpdb->prefix}icl_strings WHERE id = %d",
				$string_id
			),
			ARRAY_A
		);

		return $row ?: null;
	}

	private function fields_differ( array $update_data, $row ) {
		if ( null === $row ) {
			return true;
		}

		foreach ( $update_data as $field => $value ) {
			if ( ! array_key_exists( $field, $row ) || (string) $row[ $field ] !== (string) $value ) {
				return true;
			}
		}

		return false;
	}

	private function set_string_status_to_needs_update_if_translated( $string_id ) {
		$wpdb = $this->wpdb;

		$this->wpdb->query(
			$this->wpdb->prepare(
				"UPDATE {$wpdb->prefix}icl_strings
							SET status=%d
							WHERE id=%d AND status<>%d",
				ICL_TM_NEEDS_UPDATE,
				$string_id,
				ICL_TM_NOT_TRANSLATED
			)
		);
		$this->wpdb->query(
			$this->wpdb->prepare(
				"UPDATE {$wpdb->prefix}icl_string_translations
							SET status=%d
							WHERE string_id=%d AND status<>%d",
				ICL_TM_NEEDS_UPDATE,
				$string_id,
				ICL_TM_NOT_TRANSLATED
			)
		);
	}

	private function set_translations_to_needs_update() {
		$wpdb = $this->wpdb;

		$translation_ids = $this->wpdb->get_col(
			$this->wpdb->prepare(
				"SELECT translation_id
                      FROM {$wpdb->prefix}icl_translations
                      WHERE trid = ( SELECT trid
                      FROM {$wpdb->prefix}icl_translations
                      WHERE element_id = %d
                        AND element_type LIKE 'package%%'
                      LIMIT 1 )",
				$this->package_id
			)
		);
		if ( ! empty( $translation_ids ) ) {
			$this->wpdb->query(
				"UPDATE {$wpdb->prefix}icl_translation_status
                          SET needs_update = 1
                          WHERE translation_id IN (" . wpml_prepare_in( $translation_ids, '%d' ) . ' ) '
			);
		}
	}


	private function truncate_long_string( $string ) {
		return WPML_Displayed_String_Filter::truncate_long_string( $string );
	}


}
