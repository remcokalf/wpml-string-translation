<?php

use WPML\Core\SharedKernel\Component\Language\Domain\LanguageCode;

class WPML_ST_String {

	protected $wpdb;

	private $string_id;

	private $language;

	private $status;

	private $string_properties;

	private $slug_translation_records_factory;

	public function __construct( $string_id, wpdb $wpdb, ?WPML_Slug_Translation_Records_Factory $slug_translation_records_factory = null ) {
		$this->wpdb                             = $wpdb;
		$this->string_id                        = $string_id;
		$this->slug_translation_records_factory = $slug_translation_records_factory;
	}

	public function string_id() {

		return $this->string_id;
	}

	public function get_language() {
		$wpdb = $this->wpdb;

		$this->language = $this->language
			? $this->language
			: $wpdb->get_var(
				$wpdb->prepare(
					"SELECT language FROM {$wpdb->prefix}icl_strings WHERE id = %d LIMIT 1",
					$this->string_id
				)
			);

		return $this->language;
	}


	public function get_value() {
		$wpdb = $this->wpdb;

		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT value FROM {$wpdb->prefix}icl_strings WHERE id = %d LIMIT 1",
				$this->string_id
			)
		);
	}

	public function get_status() {
		$wpdb = $this->wpdb;

		$this->status = $this->status !== null
			? $this->status
			: (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT status FROM {$wpdb->prefix}icl_strings WHERE id = %d LIMIT 1",
					$this->string_id
				)
			);

		return $this->status;
	}

	public function set_language( $language ) {
		if ( $language !== $this->get_language() ) {
			$this->language = $language;
			$this->set_property( 'language', $language );
			$this->update_status();

			$key = md5( $this->get_context() . '_' . $this->get_name() );
			wp_cache_delete( $key, 'wpml-string-translation' );
		}
	}

	public function get_translation_statuses() {
		$wpdb = $this->wpdb;

		$statuses = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT language, status, mo_string FROM {$wpdb->prefix}icl_string_translations WHERE string_id = %d",
				$this->string_id
			)
		);
		foreach ( $statuses as &$status ) {
			if ( null !== $status->mo_string && '' !== $status->mo_string ) {
				$status->status = ICL_TM_COMPLETE;
			}
			unset( $status->mo_string );
		}

		return $statuses;
	}

	public function get_translations() {
		$wpdb = $this->wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}icl_string_translations WHERE string_id = %d",
				$this->string_id
			)
		);
	}

	public function update_status() {
		global $sitepress;

		$st = $this->get_translation_statuses();

		if ( $st ) {

			$string_language = $this->get_language();

			$all_active       = (array) $sitepress->get_active_languages();
			$active_languages = array_intersect_key(
				$all_active,
				array_flip(
					\WPML\ST\TranslationPauseScope::translatable(
						array_map( 'strval', array_keys( $all_active ) )
					)
				)
			);
			$in_scope         = array_flip( array_keys( $active_languages ) );

			$translations = [];
			$scoped       = [];
			foreach ( $st as $t ) {
				if ( $string_language != $t->language ) {
					$translations[ $t->language ] = $t->status;

					if ( isset( $in_scope[ $t->language ] ) ) {
						$scoped[ $t->language ] = $t->status;
					}
				}
			}

			if ( empty( $translations ) || max( $translations ) == ICL_TM_NOT_TRANSLATED ) {
				$status = ICL_TM_NOT_TRANSLATED;
			} elseif ( in_array( ICL_TM_WAITING_FOR_TRANSLATOR, $scoped ) ) {
				$status = ICL_TM_WAITING_FOR_TRANSLATOR;
			} elseif ( in_array( ICL_TM_NEEDS_UPDATE, $scoped ) ) {
				$status = ICL_TM_NEEDS_UPDATE;
			} elseif ( $this->has_less_translations_than_secondary_languages( $scoped, $active_languages, $string_language ) ) {
				if ( in_array( ICL_TM_COMPLETE, $translations ) ) {
					$status = ICL_STRING_TRANSLATION_PARTIAL;
				} else {
					$status = ICL_TM_NOT_TRANSLATED;
				}
			} else {
				if ( $this->areAllTranslationsComplete( $scoped ) ) {
					$status = ICL_TM_COMPLETE;
				} elseif ( in_array( ICL_TM_COMPLETE, $translations ) ) {
					$status = ICL_STRING_TRANSLATION_PARTIAL;
				} else {
					$status = ICL_TM_NOT_TRANSLATED;
				}
			}
		} else {
			$status = ICL_TM_NOT_TRANSLATED;
		}
		if ( $status !== $this->get_status() ) {
			$this->status = $status;
			$this->set_property( 'status', $status );
		}

		return $status;
	}


	private function areAllTranslationsComplete( array $translations ) {
		foreach ( $translations as $translation ) {
			if ( $translation != ICL_TM_COMPLETE ) {
				return false;
			}
		}

		return true;
	}


	private function has_less_translations_than_secondary_languages( array $translations, array $active_languages, $string_language ) {
		$active_lang_codes            = array_keys( $active_languages );
		$translations_in_active_langs = array_intersect( $active_lang_codes, array_keys( $translations ) );
		return count( $translations_in_active_langs ) < count( $active_languages ) - intval( $this->is_string_language_active( $string_language, $active_lang_codes ) );
	}

	private function is_string_language_active( $string_language, array $active_lang_codes ) {
		if ( in_array( $string_language, $active_lang_codes, true ) ) {
			return true;
		}

		if ( ! LanguageCode::isEnglish( $string_language ) ) {
			return false;
		}

		foreach ( $active_lang_codes as $active_lang_code ) {
			if ( LanguageCode::isEnglish( $active_lang_code ) ) {
				return true;
			}
		}

		return false;
	}

	public function set_translation( $language, $value = null, $status = false, $translator_id = null, $translation_service = null, $batch_id = null ) {
		$wpdb = $this->wpdb;

		if ( ! $this->exists() ) {
			return false;
		}

		$res = $this->read_translation_row( $language );

		if ( $this->is_reserved_for_translator( $res, $value, $status ) ) {
			return false;
		}

		$translation_data = array();
		if ( $translation_service ) {
			$translation_data['translation_service'] = $translation_service;
		}
		if ( $batch_id ) {
			$translation_data['batch_id'] = $batch_id;
		}
		if ( ! is_null( $value ) ) {
			if ( is_string( $value ) ) {
				$value = $this->normalize_line_breaks( $value );
			}

			$translation_data['value'] = $value;
		}
		if ( $translator_id ) {
			$translation_data['translator_id'] = $translator_id;
		}

		$translation_data            = apply_filters( 'wpml_st_string_translation_before_save', $translation_data, $language, $this->string_id );
		$translation_value_persisted = false;
		$st_id                       = 0;

		if ( ! $res ) {
			$insert_data = array_merge(
				$translation_data,
				array(
					'string_id' => $this->string_id,
					'language'  => $language,
					'status'    => ( $status ? $status : ICL_TM_NOT_TRANSLATED ),
				)
			);

			$inserted = $this->insert_translation_row( $insert_data );

			if ( false !== $inserted ) {
				$translation_data            = $insert_data;
				$st_id                       = (int) $this->wpdb->insert_id;
				$translation_value_persisted = $this->has_new_value( $translation_data, $res ) && 0 < (int) $inserted;
			} else {
				$res = $this->read_translation_row( $language );

				if ( ! $res || $this->is_reserved_for_translator( $res, $value, $status ) ) {
					return false;
				}
			}
		}

		if ( $res ) {
			$st_id = (int) $res->id;
			if ( $status ) {
				$translation_data['status'] = $status;
			} elseif ( $status === ICL_TM_NOT_TRANSLATED ) {
				$translation_data['status'] = ICL_TM_NOT_TRANSLATED;
			}

			if ( ! empty( $translation_data ) ) {
				$updated                     = $this->wpdb->update( $this->wpdb->prefix . 'icl_string_translations', $translation_data, array( 'id' => $st_id ) );
				$translation_value_persisted = $this->has_new_value( $translation_data, $res ) && 0 < (int) $updated;
				$wpdb->query(
					$wpdb->prepare( "UPDATE {$wpdb->prefix}icl_string_translations SET translation_date = NOW() WHERE id = %d", $st_id )
				);
			}
		}

		if ( ! $st_id ) {
			return false;
		}

		global $ICL_Pro_Translation;
		if ( $ICL_Pro_Translation ) {
			$ICL_Pro_Translation->fix_links_to_translated_content(
				$st_id,
				$language,
				'string',
				[
					'value'     => $value,
					'string_id' => $this->string_id,
				]
			);
		}

		icl_update_string_status( $this->string_id );
		do_action( 'icl_st_add_string_translation', $st_id );
		do_action( 'wpml_st_add_string_translation', $st_id, $translation_data, $language, $this->string_id );

		$this->flush_cache( $translation_value_persisted );
		$this->maybe_activate_slug_translation(
			$translation_value_persisted,
			array_key_exists( 'value', $translation_data ) ? $translation_data['value'] : null
		);

		return $st_id;
	}

	private function read_translation_row( $language ) {
		$wpdb = $this->wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, value, status
				 FROM {$wpdb->prefix}icl_string_translations
				 WHERE string_id = %d AND language = %s",
				$this->string_id,
				$language
			)
		);

		return $row;
	}

	private function insert_translation_row( array $translation_data ) {
		$previous_suppress_errors    = isset( $this->wpdb->suppress_errors ) ? $this->wpdb->suppress_errors : false;
		$this->wpdb->suppress_errors = true;

		try {
			return $this->wpdb->insert( $this->wpdb->prefix . 'icl_string_translations', $translation_data );
		} finally {
			$this->wpdb->suppress_errors = $previous_suppress_errors;
		}
	}

	private function has_new_value( array $translation_data, $res ) {
		if ( ! array_key_exists( 'value', $translation_data ) ) {
			return false;
		}

		$has_previous_translation_value = is_object( $res ) && property_exists( $res, 'value' );

		return ! $has_previous_translation_value || $res->value !== $translation_data['value'];
	}

	private function is_reserved_for_translator( $res, $value, $status ) {
		return isset( $res->status )
			&& $res->status == ICL_TM_WAITING_FOR_TRANSLATOR
			&& is_null( $value )
			&& ! in_array( $status, [ ICL_TM_IN_PROGRESS, ICL_TM_NOT_TRANSLATED ] )
			&& ! $res->value;
	}

	private function maybe_activate_slug_translation( $translation_value_persisted, $value ) {
		if ( ! $translation_value_persisted || ! is_string( $value ) || '' === trim( $value ) ) {
			return;
		}

		$slug_update = $this->get_precise_slug_update( (string) $this->get_name() );

		if (
			! $slug_update
			|| ! in_array(
				$this->get_context(),
				[
					WPML_Slug_Translation_Records::CONTEXT_DEFAULT,
					WPML_Slug_Translation_Records::CONTEXT_WORDPRESS,
				],
				true
			)
		) {
			return;
		}

		$element_type = 'taxonomy' === $slug_update['kind']
			? WPML_Slug_Translation_Factory::TAX
			: WPML_Slug_Translation_Factory::POST;

		do_action(
			'wpml_activate_slug_translation',
			$slug_update['name'],
			null,
			$element_type
		);
	}

	public function set_location( $location ) {
		$this->set_property( 'location', $location );
	}

	public function set_wrap_tag( $wrap_tag ) {
		$this->set_property( 'wrap_tag', $wrap_tag );
	}

	protected function set_property( $property, $value ) {
		$row = \WPML\ST\PackageTranslation\StringRowsCache::getRow( $this->string_id );
		if ( null !== $row
			&& array_key_exists( $property, $row )
			&& (string) $row[ $property ] === (string) $value
		) {
			return;
		}

		$this->wpdb->update( $this->wpdb->prefix . 'icl_strings', array( $property => $value ), array( 'id' => $this->string_id ) );
		\WPML\ST\PackageTranslation\StringRowsCache::noteFieldUpdate( $this->string_id, array( $property => $value ) );

		do_action( 'wpml_st_string_updated' );
	}

	public function exists() {
		$wpdb = $this->wpdb;

		$preloaded = \WPML\ST\PackageTranslation\StringRowsCache::hasId( $this->string_id );
		if ( null !== $preloaded ) {
			return $preloaded;
		}

		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}icl_strings WHERE id = %d",
				$this->string_id
			)
		) > 0;
	}

	public function get_context() {
		return $this->get_string_properties()->context;
	}

	public function get_gettext_context() {
		return $this->get_string_properties()->gettext_context;
	}

	public function get_name() {
		return $this->get_string_properties()->name;
	}

	private function get_string_properties() {
		$wpdb = $this->wpdb;

		if ( ! $this->string_properties ) {
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT name, context, gettext_context FROM {$wpdb->prefix}icl_strings WHERE id = %d LIMIT 1",
					$this->string_id
				)
			);

			$this->string_properties = $row ? $row : (object) [
				'name'            => null,
				'gettext_context' => null,
				'context'         => null,
			];
		}

		return $this->string_properties;
	}

	public function normalize_line_breaks( $translation_string ) {
		$original_string = $this->get_value();
		if ( is_string( $original_string ) && strpos( $original_string, "\r\n" ) !== false ) {
			$translation_string = preg_replace( '/(?<!\r)\n/', "\r\n", $translation_string );
		}

		return $translation_string;
	}

	private function flush_cache( $notify_slug_update = true ) {
		$this->maybe_flush_slug_translation_cache( $notify_slug_update );
	}


	private function maybe_flush_slug_translation_cache( $notify_slug_update = true ) {
		$string_name = $this->get_name();

		if ( ! $string_name ) {
			return;
		}

		$factory = $this->slug_translation_records_factory
			? $this->slug_translation_records_factory
			: new WPML_Slug_Translation_Records_Factory();

		if ( false !== strpos( $string_name, 'URL slug:' ) ) {
			$factory->create( WPML_Slug_Translation_Factory::POST )->flush_cache();
		}

		if ( false !== strpos( $string_name, 'tax slug' ) ) {
			$factory->create( WPML_Slug_Translation_Factory::TAX )->flush_cache();

			if ( function_exists( 'wp_cache_supports' )
				&& wp_cache_supports( 'flush_group' )
			) {
				wp_cache_flush_group( WPML_ST_Term_Link_Filter::CACHE_GROUP );

				wp_cache_flush_group( WPML_Tax_Permalink_Filters::CACHE_GROUP );
			}
		}

		if ( ! $notify_slug_update ) {
			return;
		}

		$slug_update = $this->get_precise_slug_update( $string_name );

		if (
			! $slug_update
			|| ! in_array(
				$this->get_context(),
				[
					WPML_Slug_Translation_Records::CONTEXT_DEFAULT,
					WPML_Slug_Translation_Records::CONTEXT_WORDPRESS,
				],
				true
			)
		) {
			return;
		}

		do_action(
			'wpml_translated_slug_updated',
			$slug_update['kind'],
			$slug_update['name']
		);
	}

	private function get_precise_slug_update( $string_name ) {
		$post_slug_prefix = 'URL slug: ';
		if ( 0 === strpos( $string_name, $post_slug_prefix ) ) {
			$slug_name = substr( $string_name, strlen( $post_slug_prefix ) );

			return $slug_name
				? [
					'kind' => 'post_type',
					'name' => $slug_name,
				]
				: null;
		}

		$tax_slug_prefix = 'URL ';
		$tax_slug_suffix = ' tax slug';
		if (
			0 === strpos( $string_name, $tax_slug_prefix )
			&& 0 === substr_compare( $string_name, $tax_slug_suffix, -strlen( $tax_slug_suffix ) )
		) {
			$slug_name = substr(
				$string_name,
				strlen( $tax_slug_prefix ),
				-strlen( $tax_slug_suffix )
			);

			return $slug_name
				? [
					'kind' => 'taxonomy',
					'name' => $slug_name,
				]
				: null;
		}

		return null;
	}
}
