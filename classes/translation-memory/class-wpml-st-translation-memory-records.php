<?php

class WPML_ST_Translation_Memory_Records {

	private $wpdb;

	public function __construct( wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	public function get( $strings, $source_lang, $target_lang, $context = null, $gettext_context = null ) {
		if ( ! $strings ) {
			return [];
		}

		$strings = $this->also_match_alternative_line_breaks( $strings );
		$records = $this->find_records( $strings, $source_lang, $target_lang, $context, $gettext_context );

		if ( empty( $records ) && ( $context || $gettext_context ) ) {
			$records = $this->find_records( $strings, $source_lang, $target_lang );
		}

		return $this->also_include_matches_for_alternative_line_breaks( $records );
	}

	private function also_match_alternative_line_breaks( $strings ) {
		$new_strings = array();
		foreach ( $strings as $string ) {
			if ( mb_strpos( $string, "\r\n" ) !== false ) {
				$new_strings[] = str_replace( "\r\n", "\n", $string );
			}
			if ( mb_strpos( $string, "\n" ) !== false && mb_strpos( $string, "\r" ) === false ) {
				$new_strings[] = str_replace( "\n", "\r\n", $string );
			}
		}

		return array_merge( $strings, $new_strings );
	}

	private function also_include_matches_for_alternative_line_breaks( $records ) {
		$new_records = array();
		foreach ( $records as $record ) {
			if ( mb_strpos( $record->original, "\r\n" ) !== false ) {
				$new_record = clone $record;
				$new_record->original = str_replace( "\r\n", "\n", $record->original );
				$new_records[] = $new_record;
			}
			if ( mb_strpos( $record->original, "\n" ) !== false && mb_strpos( $record->original, "\r" ) === false ) {
				$new_record = clone $record;
				$new_record->original = str_replace( "\n", "\r\n", $record->original );
				$new_records[] = $new_record;
			}
		}

		return array_merge( $records, $new_records );
	}

	private function find_records( array $strings, $source_lang, $target_lang, $context = null, $gettext_context = null ) {
		$wpdb               = $this->wpdb;
		$has_context        = $context ? 1 : 0;
		$has_gettext_context = $gettext_context ? 1 : 0;
		$has_target_language = $target_lang ? 1 : 0;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT s.value as original, COALESCE(st.value, st.mo_string) as translation, st.language as language
				FROM {$wpdb->prefix}icl_strings as s
				JOIN {$wpdb->prefix}icl_string_translations as st ON s.id = st.string_id
				WHERE s.value IN (" . implode( ', ', array_fill( 0, count( $strings ), '%s' ) ) . ')
					AND s.language = %s
					AND (
						(st.value IS NOT NULL AND st.status IN (%d, %d))
						OR (st.value IS NULL AND st.mo_string IS NOT NULL)
					)
					AND (%d = 0 OR s.context = %s)
					AND (%d = 0 OR s.gettext_context = %s)
					AND ((%d = 1 AND st.language = %s) OR (%d = 0 AND st.language <> %s))',
				array_merge(
					$strings,
					array(
						$source_lang,
						ICL_STRING_TRANSLATION_COMPLETE,
						ICL_STRING_TRANSLATION_NEEDS_UPDATE,
						$has_context,
						(string) $context,
						$has_gettext_context,
						(string) $gettext_context,
						$has_target_language,
						(string) $target_lang,
						$has_target_language,
						$source_lang,
					)
				)
			)
		);
	}
}
