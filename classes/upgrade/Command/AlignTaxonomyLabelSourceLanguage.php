<?php

namespace WPML\ST\Upgrade\Command;

class AlignTaxonomyLabelSourceLanguage implements \IWPML_St_Upgrade_Command {

	private $wpdb;

	private $sitepress;

	public function __construct( \wpdb $wpdb, \SitePress $sitepress ) {
		$this->wpdb      = $wpdb;
		$this->sitepress = $sitepress;
	}

	public function run() {
		$strings_table = $this->wpdb->prefix . 'icl_strings';

		if ( $this->wpdb->get_var( $this->wpdb->prepare( 'SHOW TABLES LIKE %s', $strings_table ) ) !== $strings_table ) {
			return false;
		}

		$default_language = (string) $this->sitepress->get_default_language();

		if ( '' === $default_language || $this->is_english( $default_language ) ) {
			return true;
		}

		$string_ids = $this->get_misaligned_string_ids( $default_language );

		if ( ! $string_ids ) {
			return true;
		}

		$ids_in = implode( ',', array_map( 'intval', $string_ids ) );

		$this->wpdb->query(
			$this->wpdb->prepare(
				"DELETE st FROM {$this->wpdb->prefix}icl_string_translations st
				 INNER JOIN {$this->wpdb->prefix}icl_strings s ON s.id = st.string_id
				 WHERE st.string_id IN ({$ids_in})
					AND ( st.language = s.language OR st.language = %s )",
				$default_language
			)
		);

		$this->wpdb->query(
			$this->wpdb->prepare(
				"UPDATE {$this->wpdb->prefix}icl_strings
				 SET language = %s
				 WHERE id IN ({$ids_in})",
				$default_language
			)
		);

		return true;
	}

	private function is_english( $code ) {
		if ( 'en' === $code || 0 === strpos( $code, 'en-' ) ) {
			return true;
		}

		return class_exists( '\WPML\LanguageEditor\LanguageCodeResolution' )
			&& \WPML\LanguageEditor\LanguageCodeResolution::isEnglish( $code );
	}

	private function get_misaligned_string_ids( $default_language ) {
		$ids = $this->wpdb->get_col(
			$this->wpdb->prepare(
				"SELECT s.id
				 FROM {$this->wpdb->prefix}icl_strings s
				 WHERE ( s.language = %s OR s.language LIKE %s )
					AND (
						s.gettext_context IN ( %s, %s )
						OR ( s.context = %s AND ( s.name LIKE %s OR s.name LIKE %s ) )
						OR ( s.context IN ( %s, %s ) AND s.name LIKE %s )
					)
					AND NOT EXISTS (
						SELECT 1 FROM {$this->wpdb->prefix}icl_string_translations st
						WHERE st.string_id = s.id
							AND st.language = %s
							AND st.value <> ''
					)",
				'en',
				'en-%',
				\WPML_ST_Taxonomy_Strings::CONTEXT_GENERAL,
				\WPML_ST_Taxonomy_Strings::CONTEXT_SINGULAR,
				\WPML_ST_Taxonomy_Strings::LEGACY_STRING_DOMAIN,
				$this->wpdb->esc_like( \WPML_ST_Taxonomy_Strings::LEGACY_NAME_PREFIX_GENERAL ) . '%',
				$this->wpdb->esc_like( \WPML_ST_Taxonomy_Strings::LEGACY_NAME_PREFIX_SINGULAR ) . '%',
				\WPML_Slug_Translation_Records::CONTEXT_DEFAULT,
				\WPML_Slug_Translation_Records::CONTEXT_WORDPRESS,
				str_replace( '%s', '%', \WPML_Tax_Slug_Translation_Records::STRING_NAME ),
				$default_language
			)
		);

		return array_map( 'intval', (array) $ids );
	}

	public function run_ajax() {
		return $this->run();
	}

	public function run_frontend() {
	}

	public static function get_command_id() {
		return __CLASS__;
	}
}
