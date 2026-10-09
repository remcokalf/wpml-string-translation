<?php

class WPML_ST_Taxonomy_Labels_Translation implements IWPML_Action {

	const NONCE_TAXONOMY_TRANSLATION = 'wpml_taxonomy_translation_nonce';
	const PRIORITY_GET_LABEL         = 10;

	private $taxonomy_strings;

	private $slug_translation_settings;

	private $super_globals;

	private $active_languages;

	public function __construct(
		WPML_ST_Taxonomy_Strings $taxonomy_strings,
		WPML_ST_Tax_Slug_Translation_Settings $slug_translation_settings,
		WPML_Super_Globals_Validation $super_globals,
		array $active_languages
	) {
		$this->taxonomy_strings          = $taxonomy_strings;
		$this->slug_translation_settings = $slug_translation_settings;
		$this->super_globals             = $super_globals;
		$this->active_languages          = $active_languages;
	}

	public function add_hooks() {
		add_filter( 'gettext_with_context', array( $this, 'block_translation_and_init_strings' ), PHP_INT_MAX, 4 );
		add_filter( 'wpml_label_translation_data', array( $this, 'get_label_translations' ), self::PRIORITY_GET_LABEL, 2 );
		\WPML\Request\Adapter\Ajax::register( 'wpml_tt_save_labels_translation', \WPML\Request\Policy\Policy::capability( 'wpml_manage_taxonomy_translation', \WPML\Request\Policy\Authenticity::actionNonce( 'wpml_taxonomy_translation_nonce', 'nonce' ) ), array( $this, 'save_label_translations' ) );
		\WPML\Request\Adapter\Ajax::register( 'wpml_tt_change_tax_strings_language', \WPML\Request\Policy\Policy::capability( 'wpml_manage_taxonomy_translation', \WPML\Request\Policy\Authenticity::actionNonce( 'wpml_taxonomy_translation_nonce', 'nonce' ) ), array( $this, 'change_taxonomy_strings_language' ) );
	}

	public function block_translation_and_init_strings( $translation, $text, $gettext_context, $domain ) {
		if ( WPML_ST_Taxonomy_Strings::CONTEXT_GENERAL === $gettext_context
			 || WPML_ST_Taxonomy_Strings::CONTEXT_SINGULAR === $gettext_context
		) {
			$this->taxonomy_strings->create_string_if_not_exist( $text, $gettext_context, $domain );
			$this->taxonomy_strings->add_to_translated_with_gettext_context( $text, $domain );

			return $text;
		}

		return $translation;
	}

	public function get_label_translations( $false, $taxonomy, $register = true ) {
		$strings = $this->taxonomy_strings->get_taxonomy_strings( $taxonomy, $register );
		list( $general, $singular, $slug ) = is_array( $strings ) ? $strings : array( null, null, null );

		if ( $register ) {
			if ( ! $general || ! $singular || ! $slug ) {
				return null;
			}
		} elseif ( ! $general && ! $singular ) {
			return null;
		}

		$source_lang = $general ? $general->get_language() : $singular->get_language();

		$general_translations  = $general ? $this->get_translations( $general ) : array();
		$singular_translations = $singular ? $this->get_translations( $singular ) : array();
		$slug_translations     = $slug ? $this->get_translations( $slug ) : array();

		$data = array(
			'st_default_lang' => $source_lang,
		);

		foreach ( array_keys( $this->active_languages ) as $lang ) {
			if ( $lang === $source_lang ) {
				continue;
			}

			$data[ $lang ]['general']  = $this->get_translation_value( $lang, $general_translations );
			$data[ $lang ]['singular'] = $this->get_translation_value( $lang, $singular_translations );
			$data[ $lang ]['slug']     = $this->get_translation_value( $lang, $slug_translations );

			$data[ $lang ] = array_filter( $data[ $lang ] );

			$data[ $lang ]['hasTranslation'] = $this->has_real_translation( $lang, $general_translations )
				|| $this->has_real_translation( $lang, $singular_translations )
				|| $this->has_real_translation( $lang, $slug_translations );

			if ( empty( $data[ $lang ]['general'] ) && empty( $data[ $lang ]['singular'] )
				&& $this->is_label_translation_in_progress( $general, $singular, $lang ) ) {
				$data[ $lang ]['inProgress'] = true;
			}

			if ( ( ! empty( $data[ $lang ]['general'] ) || ! empty( $data[ $lang ]['singular'] ) )
				&& $this->is_label_translation_needs_update( $general, $singular, $lang ) ) {
				$data[ $lang ]['needsUpdate'] = true;
			}
		}

		$data[ $source_lang ] = array(
			'general'                      => $general ? $general->get_value() : '',
			'singular'                     => $singular ? $singular->get_value() : '',
			'slug'                         => $slug ? $slug->get_value() : '',
			'original'                     => true,
			'globalSlugTranslationEnabled' => $this->slug_translation_settings->is_enabled(),
			'showSlugTranslationField'     => true,
		);

		return $data;
	}

	private function is_label_translation_in_progress( $general, $singular, $lang ) {
		return $this->label_batch_status_matches( $general, $singular, $lang, false );
	}

	private function is_label_translation_needs_update( $general, $singular, $lang ) {
		return $this->label_batch_status_matches( $general, $singular, $lang, true );
	}

	private function label_batch_status_matches( $general, $singular, $lang, $needs_update ) {
		$string_ids = array_filter(
			array(
				$general ? (int) $general->string_id() : 0,
				$singular ? (int) $singular->string_id() : 0,
			)
		);
		if ( ! $string_ids ) {
			return false;
		}

		global $wpdb;

		$ids_in     = implode( ',', array_map( 'intval', $string_ids ) );
		$batch_name = \WPML\StringTranslation\Infrastructure\TranslateEverything\TaxonomyLabelJobDispatcher::BATCH_NAME_PREFIX . $lang;

		$job_join  = $needs_update
			? "LEFT JOIN {$wpdb->prefix}icl_translate_job j ON j.rid = ts.rid AND j.translated = 0"
			: "INNER JOIN {$wpdb->prefix}icl_translate_job j ON j.rid = ts.rid AND j.translated = 0";
		$condition = $needs_update ? 'AND ts.needs_update = 1 AND j.rid IS NULL' : '';

		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT 1
				FROM {$wpdb->prefix}icl_string_batches sb
				INNER JOIN {$wpdb->prefix}icl_translation_batches b ON b.id = sb.batch_id
				INNER JOIN {$wpdb->prefix}icl_translations src ON src.element_type = 'st-batch_strings' AND src.element_id = b.id
				INNER JOIN {$wpdb->prefix}icl_translations tgt ON tgt.trid = src.trid AND tgt.language_code = %s
				INNER JOIN {$wpdb->prefix}icl_translation_status ts ON ts.translation_id = tgt.translation_id
				{$job_join}
				WHERE sb.string_id IN ({$ids_in}) AND b.batch_name = %s {$condition}
				LIMIT 1",
				$lang,
				$batch_name
			)
		);
	}

	private function get_translations( WPML_ST_String $string ) {
		$translations = array();

		foreach ( $string->get_translations() as $translation ) {
			$translations[ $translation->language ] = $translation;
		}

		return $translations;
	}

	private function has_real_translation( $lang, array $translations ) {
		return isset( $translations[ $lang ] ) && (bool) $translations[ $lang ]->value;
	}

	private function get_translation_value( $lang, array $translations ) {
		$value = null;

		if ( isset( $translations[ $lang ] ) ) {
			if ( $translations[ $lang ]->value ) {
				$value = $translations[ $lang ]->value;
			} elseif ( $translations[ $lang ]->mo_string ) {
				$value = $translations[ $lang ]->mo_string;
			}
		}

		return $value;
	}

	public function save_label_translations() {
		if ( ! current_user_can( 'wpml_manage_taxonomy_translation' ) ) {
			/* translators: Error message shown when the user does not have the rights to do what they asked for. Past participle used as a state, lower case in the source. */
			wp_send_json_error( __( 'not allowed', 'wpml-string-translation' ) );
			return;
		}

		if ( ! $this->check_nonce() ) {
			return;
		}

		$general_translation  = $this->get_string_var_from_post( 'plural' );
		$singular_translation = $this->get_string_var_from_post( 'singular' );
		$slug_translation     = $this->get_string_var_from_post( 'slug', FILTER_UNSAFE_RAW );
		$taxonomy_name        = $this->get_string_var_from_post( 'taxonomy' );
		$language             = $this->get_string_var_from_post( 'taxonomy_language_code' );

		if ( $general_translation && $singular_translation && $taxonomy_name && $language ) {
			list( $general, $singular, $slug ) = $this->taxonomy_strings->get_taxonomy_strings( $taxonomy_name );

			if ( $general && $singular && $slug ) {
				$general->set_translation( $language, $general_translation, ICL_STRING_TRANSLATION_COMPLETE );
				$singular->set_translation( $language, $singular_translation, ICL_STRING_TRANSLATION_COMPLETE );
				$slug->set_translation( $language, $slug_translation, ICL_STRING_TRANSLATION_COMPLETE );

				$slug_translation_enabled = $this->has_slug_translation( $slug );
				$this->slug_translation_settings->set_type( $taxonomy_name, $slug_translation_enabled );
				$this->slug_translation_settings->save();

				$result = array(
					'general'  => $general_translation,
					'singular' => $singular_translation,
					'slug'     => $slug_translation,
					'lang'     => $language,
				);

				wp_send_json_success( $result );
				return;
			}
		}

		wp_send_json_error();
	}

	private function has_slug_translation( WPML_ST_String $slug ) {
		$translations = $slug->get_translations();

		if ( $translations ) {
			foreach ( $translations as $translation ) {
				if ( trim( $translation->value ) && ICL_STRING_TRANSLATION_COMPLETE === (int) $translation->status ) {
					return true;
				}
			}
		}

		return false;
	}

	public function change_taxonomy_strings_language() {
		if ( ! current_user_can( 'wpml_manage_taxonomy_translation' ) ) {
			/* translators: Error message shown when the user does not have the rights to do what they asked for. Past participle used as a state, lower case in the source. */
			wp_send_json_error( __( 'not allowed', 'wpml-string-translation' ) );
			return;
		}

		if ( ! $this->check_nonce() ) {
			return;
		}

		$taxonomy    = $this->get_string_var_from_post( 'taxonomy' );
		$source_lang = $this->get_string_var_from_post( 'source_lang' );

		if ( ! $taxonomy || ! $source_lang ) {
			/* translators: Error message shown when a request to the server arrives without the information it needs. */
			wp_send_json_error( __( 'Missing parameters', 'wpml-string-translation' ) );
			return;
		}

		list( $general_string, $singular_string, $slug ) = $this->taxonomy_strings->get_taxonomy_strings( $taxonomy );

		$lang = \WPML\Language\RequestedLanguage::validate( $source_lang, \WPML\Language\RequestedLanguage::SCOPE_CONFIGURED );

		if ( null === $lang && $source_lang !== $general_string->get_language() ) {
			wp_send_json_error( 'invalid language', 400 );
			return;
		}

		$lang = null === $lang ? $source_lang : $lang;

		$general_string->set_language( $lang );
		$singular_string->set_language( $lang );
		$slug->set_language( $lang );

		wp_send_json_success();
	}

	private function get_string_var_from_post( $key, $filter = FILTER_SANITIZE_FULL_SPECIAL_CHARS, $options = null ) {
		$value = $this->super_globals->post( $key, $filter, $options );
		return null !== $value ? sanitize_text_field( $value ) : false;
	}

	private function check_nonce() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], self::NONCE_TAXONOMY_TRANSLATION ) ) {
			/* translators: Error message on the String Translation page when the security check of a request fails. */
			wp_send_json_error( __( 'Invalid nonce', 'wpml-string-translation' ) );
			return false;
		}

		return true;
	}
}
