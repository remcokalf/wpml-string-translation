<?php

abstract class WPML_Slug_Translation_Records {

	const CONTEXT_DEFAULT   = 'default';
	const CONTEXT_WORDPRESS = 'WordPress';

	private $wpdb;

	private $cache_factory;

	public function __construct( wpdb $wpdb, WPML_WP_Cache_Factory $cache_factory ) {
		$this->wpdb          = $wpdb;
		$this->cache_factory = $cache_factory;
	}

	public function get_slug( $type ) {
		$wpdb       = $this->wpdb;
		$cache_item = $this->cache_factory->create_cache_item( $this->get_cache_group(), $type );

		list( $cached, $found ) = $cache_item->get_with_found();

		if ( $found && is_array( $cached ) ) {
			return WPML_ST_Slug::from_array( $cached );
		}

		$slug = new WPML_ST_Slug();

		$original = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, value, language, context, name
				 FROM {$wpdb->prefix}icl_strings
				 WHERE name = %s
				    AND (context = %s OR context = %s)",
				$this->get_string_name( $type ),
				self::CONTEXT_DEFAULT,
				self::CONTEXT_WORDPRESS
			)
		);

		if ( $original ) {
			$slug->set_lang_data( $original );

			$translations = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT value, language, status
				 FROM {$wpdb->prefix}icl_string_translations
				 WHERE string_id = %d
					AND value <> '' 
				    AND language <> %s",
					$original->id,
					$original->language
				)
			);

			if ( $translations ) {
				foreach ( $translations as $translation ) {
					$slug->set_lang_data( $translation );
				}
			}
		}

		$cache_item->set( $slug->to_array() );

		return $slug;
	}

	private function get_cache_group() {
		return __CLASS__ . '::' . $this->get_element_type();
	}

	public function flush_cache() {
		$cache_group = $this->cache_factory->create_cache_group( $this->get_cache_group() );
		$cache_group->flush_group_cache();
	}

	public function get_translation( $type, $lang ) {
		$slug = $this->get_slug( $type );

		if ( $slug->is_translation_complete( $lang ) ) {
			return $slug->get_value( $lang );
		}

		return null;
	}

	public function get_original( $type, $lang = '' ) {
		$slug = $this->get_slug( $type );

		if ( ! $lang || $slug->get_original_lang() === $lang ) {
			return $slug->get_original_value();
		}

		return null;
	}

	public function get_slug_id( $type ) {
		$slug = $this->get_slug( $type );

		if ( $slug->get_original_id() ) {
			return $slug->get_original_id();
		}

		return null;
	}

	public function register_slug( $type, $slug ) {
		$source_lang = apply_filters( 'wpml_st_register_slug_set_source_language', null, $type, $slug );

		if ( null === $source_lang ) {
			$source_lang = $this->get_registration_source_language();
		}

		$string_id = icl_register_string(
			self::CONTEXT_WORDPRESS,
			$this->get_string_name( $type ),
			$slug,
			false,
			$source_lang
		);

		$this->flush_cache();

		return $string_id;
	}

	protected function get_registration_source_language() {
		return null;
	}

	public function update_original_slug( $type, $slug ) {
		$updated = $this->wpdb->update(
			$this->wpdb->prefix . 'icl_strings',
			array( 'value' => $slug ),
			array( 'name' => $this->get_string_name( $type ) )
		);

		$this->flush_cache();

		if ( 0 < (int) $updated ) {
			do_action(
				'wpml_translated_slug_updated',
				WPML_Slug_Translation_Factory::POST === $this->get_element_type() ? 'post_type' : 'taxonomy',
				$type
			);
		}
	}

	public function get_original_slug_and_lang( $type ) {
		$original_slug_and_lang = null;

		$slug = $this->get_slug( $type );

		if ( $slug->get_original_id() ) {
			$original_slug_and_lang = (object) array(
				'value'    => $slug->get_original_value(),
				'language' => $slug->get_original_lang(),
			);
		}

		return $original_slug_and_lang;
	}

	public function get_element_slug_translations( $type, $only_status_complete = true ) {
		$slug = $this->get_slug( $type );

		$rows = array();

		foreach ( $slug->get_language_codes() as $lang ) {
			if ( $slug->get_original_lang() === $lang
				|| ( $only_status_complete && ! $slug->is_translation_complete( $lang ) )
			) {
				continue;
			}

			$rows[] = (object) array(
				'value'    => $slug->get_value( $lang ),
				'language' => $lang,
				'status'   => $slug->get_status( $lang ),
			);
		}

		return $rows;
	}

	public function get_all_slug_translations( $types ) {
		$rows = array();

		foreach ( $types as $type ) {
			$slug = $this->get_slug( $type );

			foreach ( $slug->get_language_codes() as $lang ) {
				if ( $slug->get_original_lang() !== $lang ) {
					$rows[] = (object) array(
						'value' => $slug->get_value( $lang ),
						'name'  => $slug->get_name(),
					);
				}
			}
		}

		return $rows;
	}

	public function get_slug_translation_languages( $type ) {
		$languages = array();
		$slug      = $this->get_slug( $type );

		foreach ( $slug->get_language_codes() as $lang ) {
			if ( $slug->is_translation_complete( $lang ) ) {
				$languages[] = $lang;
			}
		}

		return $languages;
	}

	public function get_slug_string( $type ) {
		$string_id = $this->get_slug_id( $type );

		if ( $string_id ) {
			return new WPML_ST_String( $string_id, $this->wpdb );
		}

		return null;
	}

	abstract protected function get_string_name( $slug );

	abstract protected function get_element_type();
}
