<?php

class WPML_ST_Word_Count_String_Records {

	const CACHE_GROUP = __CLASS__;

	private $wpdb;

	public function __construct( wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	public function get_total_words() {
		$wpdb = $this->wpdb;

		return (int) $wpdb->get_var( "SELECT SUM(word_count) FROM {$wpdb->prefix}icl_strings" );
	}

	public function get_all_values_without_word_count() {
		$wpdb = $this->wpdb;

		return $wpdb->get_results(
			"SELECT id, value FROM {$wpdb->prefix}icl_strings
			WHERE word_count IS NULL"
		);
	}

	public function get_words_to_translate_per_lang( $lang, $package_id = null ) {
		$key   = $lang . ':' . $package_id;
		$found = false;
		$words = WPML_Non_Persistent_Cache::get( $key, self::CACHE_GROUP, $found );
		if ( ! $found ) {
			$query = "
			SELECT SUM(word_count) FROM {$this->wpdb->prefix}icl_strings AS s
			LEFT JOIN {$this->wpdb->prefix}icl_string_translations AS st
				ON st.string_id = s.id AND st.language = %s
			WHERE (st.status <> %d OR st.status IS NULL)
		";

			$prepare_args = [
				$lang,
				ICL_STRING_TRANSLATION_COMPLETE,
			];

			if ( $package_id ) {
				$query .= ' AND s.string_package_id = %d';

				$prepare_args[] = $package_id;
			}

			$words = (int) $this->wpdb->get_var( $this->wpdb->prepare( $query, $prepare_args ) );
			WPML_Non_Persistent_Cache::set( $key, $words, self::CACHE_GROUP );
		}

		return $words;
	}

	public function get_value_and_language( $string_id ) {
		$wpdb = $this->wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT value, language FROM {$wpdb->prefix}icl_strings WHERE id = %d",
				$string_id
			)
		);
	}

	public function set_word_count( $string_id, $word_count ) {
		$this->wpdb->update(
			$this->wpdb->prefix . 'icl_strings',
			array( 'word_count' => $word_count ),
			array( 'id' => $string_id )
		);
	}

	public function get_word_count( $string_id ) {
		$wpdb = $this->wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT word_count FROM {$wpdb->prefix}icl_strings WHERE ID = %d",
				$string_id
			)
		);
	}

	public function reset_all() {
		$wpdb = $this->wpdb;

		$wpdb->query( "UPDATE {$wpdb->prefix}icl_strings SET word_count = NULL" );
	}

	public function get_ids_from_package_ids( array $package_ids ) {
		if ( ! $package_ids ) {
			return array();
		}

		$wpdb = $this->wpdb;

		return array_map(
			'intval',
			$wpdb->get_col(
				$wpdb->prepare(
					"SELECT id FROM {$wpdb->prefix}icl_strings
					WHERE string_package_id IN (" . implode( ', ', array_fill( 0, count( $package_ids ), '%d' ) ) . ')',
					array_map( 'intval', $package_ids )
				)
			)
		);
	}
}
