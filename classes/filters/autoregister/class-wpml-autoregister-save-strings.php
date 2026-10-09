<?php

use WPML\StringTranslation\Infrastructure\TranslateEverything\EnglishSourceLanguage;
use WPML\ST\StringValue;

class WPML_Autoregister_Save_Strings {
	const INSERT_CHUNK_SIZE = 200;

	private $wpdb;

	private $sitepress;

	private $data = array();

	private $lang_of_domain;

	private $english_source_lang;

	public function __construct( wpdb $wpdb, SitePress $sitepress, ?WPML_Language_Of_Domain $language_of_domain = null ) {
		$this->wpdb           = $wpdb;
		$this->sitepress      = $sitepress;
		$this->lang_of_domain = $language_of_domain ? $language_of_domain : new WPML_Language_Of_Domain( $this->sitepress );

		add_action( 'shutdown', array( $this, 'shutdown' ) );
	}

	public function save( $value, $name, $domain, $gettext_context = '' ) {
		$this->data[] = array(
			'value'           => $value,
			'name'            => $name,
			'domain'          => $domain,
			'gettext_context' => $gettext_context,
		);
	}

	public function get_source_lang( $name, $domain ) {
		$domain_lang = $this->lang_of_domain->get_language( $domain );

		if ( ! $domain_lang ) {
			$flag = 0 === strpos( $domain, 'admin_texts_' )
					|| WPML_ST_Blog_Name_And_Description_Hooks::is_string( $name );

			$domain_lang = $flag
				? $this->sitepress->get_user_admin_language( get_current_user_id() )
				: $this->get_english_source_lang();
		}

		return $domain_lang;
	}

	private function get_english_source_lang() {
		if ( null === $this->english_source_lang ) {
			$this->english_source_lang = EnglishSourceLanguage::resolve(
				array_keys( (array) $this->sitepress->get_active_languages() ),
				(string) $this->sitepress->get_default_language()
			);
		}

		return $this->english_source_lang;
	}

	private function persist() {
		foreach ( array_chunk( $this->data, self::INSERT_CHUNK_SIZE ) as $chunk ) {
			$has_text_column = StringValue::isReady();

			$query = "INSERT IGNORE INTO {$this->wpdb->prefix}icl_strings "
					 . '(`language`, `context`, `gettext_context`, `domain_name_context_md5`, `name`, `value`, `status`'
					 . ( $has_text_column ? ', `has_text`' : '' ) . ') VALUES ';

			$i = 0;
			foreach ( $chunk as $string ) {
				if ( $i > 0 ) {
					$query .= ',';
				}

				$row_values = array(
					$this->get_source_lang( $string['name'], $string['domain'] ),
					$string['domain'],
					$string['gettext_context'],
					md5( $string['domain'] . $string['name'] . $string['gettext_context'] ),
					$string['name'],
					$string['value'],
					ICL_TM_NOT_TRANSLATED,
				);

				if ( $has_text_column ) {
					$row_values[] = StringValue::hasTextFlag( $string['value'] );

					$query .= $this->wpdb->prepare( '(%s, %s, %s, %s, %s, %s, %d, %d)', $row_values );
				} else {
					$query .= $this->wpdb->prepare( '(%s, %s, %s, %s, %s, %s, %d)', $row_values );
				}

				$i ++;
			}

			$this->wpdb->query( $query );
		}
	}

	public function shutdown() {
		if ( count( $this->data ) ) {
			$this->persist();
			$this->data = array();
		}
	}
}
