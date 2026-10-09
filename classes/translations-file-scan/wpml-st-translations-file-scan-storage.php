<?php

class WPML_ST_Translations_File_Scan_Storage {
	private $wpdb;

	private $bulk_insert;

	public function __construct( wpdb $wpdb, WPML_ST_Bulk_Strings_Insert $bulk_insert ) {
		$this->wpdb        = $wpdb;
		$this->bulk_insert = $bulk_insert;
	}


	public function save( array $translations, $domain, $lang ) {
		$this->bulk_insert->insert_strings( $this->build_string_collection( $translations, $domain ) );

		$string_translations = $this->build_string_translation_collection(
			$translations,
			$lang,
			$this->get_string_maps( $domain )
		);

		$this->bulk_insert->insert_string_translations( $string_translations );
	}

	private function build_string_collection( array $translations, $domain ) {
		$result = array();

		$english = \WPML\StringTranslation\Infrastructure\TranslateEverything\EnglishSourceLanguage::resolveForSite();

		foreach ( $translations as $translation ) {
			$result[] = new WPML_ST_Models_String(
				$english,
				$domain,
				$translation->get_context(),
				$translation->get_original(),
				ICL_TM_NOT_TRANSLATED
			);
		}

		return $result;
	}

	private function get_string_maps( $domain ) {
		$wpdb   = $this->wpdb;
		$rowset = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, value, gettext_context FROM {$wpdb->prefix}icl_strings
				 WHERE context = %s",
				$domain
			)
		);
		$result = array();

		foreach ( $rowset as $row ) {
			$result[ $row->value ][ $row->gettext_context ] = $row->id;
		}

		return $result;
	}

	private function build_string_translation_collection( array $translations, $lang, $value_id_map ) {
		$result = array();

		foreach ( $translations as $translation ) {
			if ( ! isset( $value_id_map[ $translation->get_original() ] ) ) {
				continue;
			}

			$context = (string) $translation->get_context();
			if ( ! isset( $value_id_map[ $translation->get_original() ][ $context ] ) ) {
				continue;
			}

			$result[] = new WPML_ST_Models_String_Translation(
				$value_id_map[ $translation->get_original() ][ $context ],
				$lang,
				ICL_TM_NOT_TRANSLATED,
				null,
				$translation->get_translation()
			);
		}

		return $result;
	}
}
