<?php

namespace WPML\ST\TranslationFile\Sync;

use WPML\Collect\Support\Collection;
use WPML\ST\TranslationFile\StringCollation;

class TranslationUpdates {
	use StringCollation;

	const ICL_STRING_TRANSLATION_COMPLETE = 10;

	private $wpdb;

	private $languageRecords;

	private $data;

	public function __construct( \wpdb $wpdb, \WPML_Language_Records $languageRecords ) {
		$this->wpdb            = $wpdb;
		$this->languageRecords = $languageRecords;
	}

	public function getTimestamp( $domain, $locale ) {
		$this->loadData();
		$lang = $this->languageRecords->get_language_code( $locale );
		return (int) $this->data->get( "$lang#$domain" );
	}

	private function loadData() {
		if ( ! $this->data ) {
			$wpdb = $this->wpdb;

			$this->data = wpml_collect(
				$wpdb->get_results(
					$wpdb->prepare(
						"SELECT
							CONCAT(st.language,'#',s.context " . esc_sql( $this->getCollateForContextColumn( $wpdb ) ) . ") AS lang_domain,
							UNIX_TIMESTAMP(MAX(st.translation_date)) as last_update
						FROM {$wpdb->prefix}icl_string_translations AS st
						INNER JOIN {$wpdb->prefix}icl_strings AS s ON st.string_id = s.id
						WHERE st.value IS NOT NULL AND st.status = %d
						GROUP BY lang_domain",
						self::ICL_STRING_TRANSLATION_COMPLETE
					)
				)
			)->pluck( 'last_update', 'lang_domain' );
		}
	}

	public function reset() {
		$this->data = null;
	}
}
