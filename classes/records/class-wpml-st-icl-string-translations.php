<?php

class WPML_ST_ICL_String_Translations extends WPML_WPDB_User {

	private $string_id = 0;
	private $lang_code;
	private $id;

	public function __construct( &$wpdb, $string_id, $lang_code ) {
		parent::__construct( $wpdb );
		$string_id = (int) $string_id;
		if ( $string_id > 0 && $lang_code ) {
			$this->string_id = $string_id;
			$this->lang_code = $lang_code;
		} else {
			throw new InvalidArgumentException(
				'Invalid String ID: '
												. $string_id . ' or language_code: ' . $lang_code
			);
		}
	}

	public function translator_id() {
		$wpdb = $this->wpdb;

		return $wpdb->get_var(
			$wpdb->prepare(
				" SELECT translator_id
									FROM {$wpdb->prefix}icl_string_translations
									WHERE id = %d LIMIT 1",
				$this->id()
			)
		);
	}

	public function value() {
		$wpdb = $this->wpdb;

		return $wpdb->get_var(
			$wpdb->prepare(
				" SELECT value
									FROM {$wpdb->prefix}icl_string_translations
									WHERE id = %d LIMIT 1",
				$this->id()
			)
		);
	}

	public function id() {
		$wpdb = $this->wpdb;

		return (int) ( $this->id
			? $this->id
			: $wpdb->get_var(
				$wpdb->prepare(
					" SELECT id
									FROM {$wpdb->prefix}icl_string_translations
									WHERE string_id = %d AND language = %s
									LIMIT 1",
					$this->string_id,
					$this->lang_code
				)
			) );
	}
}
