<?php

use WPML\Core\SharedKernel\Component\Language\Domain\LanguageCode;
use WPML\ST\StringValue;
use WPML\ST\StringsFilter\Translator;
use WPML\StringTranslation\Infrastructure\TranslateEverything\EnglishSourceLanguage;

class WPML_Register_String_Filter extends WPML_Displayed_String_Filter {

	private const TEXT_COLUMNS = [ 'context', 'gettext_context', 'name', 'value' ];

	protected $wpdb;

	protected $sitepress;

	private $excluded_contexts = array();

	private $persistent_string_cache;

	private $request_string_cache = [];

	private $string_factory;

	private $save_strings;

	protected $name;
	protected $domain;
	protected $gettext_context;
	protected $name_and_gettext_context;
	protected $key;

	private $block_save_strings = false;

	private $active_language_codes;

	const REGISTERED_STRING_CACHE_LIMIT = 2000;

	const CACHE_GROUP = 'WPML_Register_String_Filter';

	public function __construct(
		$wpdb,
		SitePress $sitepress,
		&$string_factory,
		Translator $translator,
		array $excluded_contexts = array(),
		?WPML_Autoregister_Save_Strings $save_strings = null
	) {
		parent::__construct( $translator );

		$this->wpdb                    = $wpdb;
		$this->sitepress               = $sitepress;
		$this->string_factory          = &$string_factory;
		$this->excluded_contexts       = $excluded_contexts;
		$this->save_strings            = $save_strings;
		$this->persistent_string_cache = new WPML_WP_Cache( self::CACHE_GROUP );

		add_action( 'wpml_update_active_languages', array( $this, 'reset_active_language_codes' ) );
		add_action( 'switch_blog', array( $this, 'reset_active_language_codes' ) );
	}

	public function translate_by_name_and_context(
		$untranslated_text,
		$name,
		$context = '',
		&$has_translation = null
	) {
		if ( is_array( $untranslated_text ) || is_object( $untranslated_text ) ) {
			return '';
		}

		$translation     = $this->get_translation( $untranslated_text, $name, $context );
		$has_translation = $translation->hasTranslation();

		return $translation->getValue();
	}

	public function force_saving_of_autoregistered_strings() {
		$this->get_save_strings()->shutdown();
	}

	public function register_string( $context, $name, $value, $allow_empty_value = false, $source_lang = '' ) {

		$name = trim( $name ) ? $name : md5( $value );
		$this->initialize_current_string( $name, $context );

		if ( substr( $name, 0, 10 ) === 'URL slug: ' && WPML_Slug_Translation::STRING_DOMAIN !== $context ) {
			return false;
		}

		list( $domain, $context, $key ) = $this->key_by_name_and_context( $name, $context );
		list( $name, $context )         = $this->truncate_name_and_context( $name, $context );

		if ( $source_lang == '' ) {
			$source_lang = $this->get_save_strings()->get_source_lang( $name, $domain );
		}

		$source_lang = $this->normalize_english_source_lang( $source_lang );

		$res = $this->get_registered_string( $domain, $context, $name );
		if ( $res ) {
			$string_id = $res['id'];

			$update_string      = array();
			$update_translation = array();
			if ( $value != $res['value'] ) {
				$update_string['value']      = $value;
				$update_translation['value'] = $value;

				if ( StringValue::isReady() ) {
					$update_string['has_text'] = StringValue::hasTextFlag( $value );
				}
			}
			if ( ! empty( $update_string ) ) {
				$existing_lang = $this->string_factory->find_by_id( $string_id )->get_language();
				if ( $this->is_same_source_language( $existing_lang, $source_lang ) ) {
					$this->wpdb->update( $this->wpdb->prefix . 'icl_strings', $update_string, array( 'id' => $string_id ) );
					$this->wpdb->update(
						$this->wpdb->prefix . 'icl_string_translations',
						array( 'status' => ICL_TM_NEEDS_UPDATE ),
						array( 'string_id' => $string_id )
					);
					icl_update_string_status( $string_id );

				} else {
					$update_translation['status'] = ICL_TM_COMPLETE;

					$this->write_translation_row( $string_id, $source_lang, $update_translation );

					icl_update_string_status( $string_id );
				}
			}
		} else {
			$string_id = $this->save_string( $value, $allow_empty_value, $source_lang, $domain, $context, $name );
		}

		return $string_id;
	}

	private function write_translation_row( $string_id, $language, array $translation_data ) {
		$row = array(
			'string_id' => $string_id,
			'language'  => $language,
		);

		if ( ! $this->translation_row_exists( $string_id, $language ) ) {
			if ( false !== $this->insert_translation_row( array_merge( $translation_data, $row ) ) ) {
				return true;
			}

			if ( ! $this->translation_row_exists( $string_id, $language ) ) {
				return false;
			}
		}

		return false !== $this->wpdb->update(
			$this->wpdb->prefix . 'icl_string_translations',
			$translation_data,
			$row
		);
	}

	private function translation_row_exists( $string_id, $language ) {
		$wpdb = $this->wpdb;

		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}icl_string_translations WHERE string_id = %d AND language = %s",
				$string_id,
				$language
			)
		);
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

	private function normalize_english_source_lang( $source_lang ) {
		return EnglishSourceLanguage::normalize(
			(string) $source_lang,
			$this->active_language_codes(),
			(string) $this->sitepress->get_default_language()
		);
	}

	private function is_same_source_language( $existing_lang, $source_lang ) {
		if ( (string) $existing_lang === (string) $source_lang ) {
			return true;
		}

		return LanguageCode::isEnglish( $existing_lang )
			&& LanguageCode::isEnglish( $source_lang )
			&& ! in_array( (string) $existing_lang, $this->active_language_codes(), true );
	}

	private function active_language_codes() {
		if ( null === $this->active_language_codes ) {
			$this->active_language_codes = array_map( 'strval', array_keys( (array) $this->sitepress->get_active_languages() ) );
		}

		return $this->active_language_codes;
	}

	public function reset_active_language_codes() {
		$this->active_language_codes = null;
	}

	private function get_registered_string( $domain, $context, $name ) {
		$key = md5( $domain . $name . $context );

		if ( array_key_exists( $key, $this->request_string_cache ) ) {
			return $this->touch_request_cache( $key );
		}

		$preloaded = \WPML\ST\PackageTranslation\StringRowsCache::findRegistered( $domain, $name, $context );
		if ( null !== $preloaded ) {
			if ( false !== $preloaded ) {
				$this->persistent_string_cache->set( $key, $preloaded );
			}
			$this->store_in_request_cache( $key, $preloaded );

			return $preloaded;
		}

		$found  = false;
		$result = $this->persistent_string_cache->get( $key, $found );

		if ( ! $found ) {
			$result = $this->query_registered_string( $key );
			if ( false !== $result ) {
				$this->persistent_string_cache->set( $key, $result );
			}
		}

		$this->store_in_request_cache( $key, $result );

		return $result;
	}

	private function query_registered_string( $key ) {
		$wpdb = $this->wpdb;
		$row  = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, value FROM {$wpdb->prefix}icl_strings WHERE domain_name_context_md5 = %s",
				$key
			),
			ARRAY_A
		);

		if ( ! $row ) {
			return false;
		}

		return [
			'id'    => $row['id'],
			'value' => $row['value'],
		];
	}

	private function cache_registered_string( $key, array $row ) {
		$this->persistent_string_cache->set( $key, $row );
		$this->store_in_request_cache( $key, $row );
	}

	private function touch_request_cache( $key ) {
		$value = $this->request_string_cache[ $key ];
		unset( $this->request_string_cache[ $key ] );
		$this->request_string_cache[ $key ] = $value;

		return $value;
	}

	private function store_in_request_cache( $key, $value ) {
		unset( $this->request_string_cache[ $key ] );
		$this->request_string_cache[ $key ] = $value;

		if ( count( $this->request_string_cache ) > self::REGISTERED_STRING_CACHE_LIMIT ) {
			array_shift( $this->request_string_cache );
		}
	}

	private function save_string( $value, $allow_empty_value, $language, $domain, $context, $name ) {
		$value = is_null( $value ) ? '' : $value;

		if ( ! $this->block_save_strings && ( $allow_empty_value || 0 !== strlen( $value ) ) ) {

			$args = array(
				'language'                => $language,
				'context'                 => $domain,
				'gettext_context'         => $context,
				'domain_name_context_md5' => md5( $domain . $name . $context ),
				'name'                    => $name,
				'value'                   => $value,
				'status'                  => ICL_TM_NOT_TRANSLATED,
			);

			if ( StringValue::isReady() ) {
				$args['has_text'] = StringValue::hasTextFlag( $value );
			}

			if ( class_exists( 'WPML_TM_Translation_Priorities' ) ) {
				$args['translation_priority'] = WPML_TM_Translation_Priorities::DEFAULT_TRANSLATION_PRIORITY_VALUE_SLUG;
			}

			$wpdb = $this->wpdb;
			if ( isset( $args['has_text'], $args['translation_priority'] ) ) {
				$wpdb->query(
					$wpdb->prepare(
						"INSERT IGNORE INTO {$wpdb->prefix}icl_strings
						(language, context, gettext_context, domain_name_context_md5, name, value, status, has_text, translation_priority)
						VALUES (%s, %s, %s, %s, %s, %s, %d, %d, %s)",
						$args['language'],
						$args['context'],
						$args['gettext_context'],
						$args['domain_name_context_md5'],
						$args['name'],
						$args['value'],
						$args['status'],
						$args['has_text'],
						$args['translation_priority']
					)
				);
			} elseif ( isset( $args['has_text'] ) ) {
				$wpdb->query(
					$wpdb->prepare(
						"INSERT IGNORE INTO {$wpdb->prefix}icl_strings
						(language, context, gettext_context, domain_name_context_md5, name, value, status, has_text)
						VALUES (%s, %s, %s, %s, %s, %s, %d, %d)",
						$args['language'],
						$args['context'],
						$args['gettext_context'],
						$args['domain_name_context_md5'],
						$args['name'],
						$args['value'],
						$args['status'],
						$args['has_text']
					)
				);
			} elseif ( isset( $args['translation_priority'] ) ) {
				$wpdb->query(
					$wpdb->prepare(
						"INSERT IGNORE INTO {$wpdb->prefix}icl_strings
						(language, context, gettext_context, domain_name_context_md5, name, value, status, translation_priority)
						VALUES (%s, %s, %s, %s, %s, %s, %d, %s)",
						$args['language'],
						$args['context'],
						$args['gettext_context'],
						$args['domain_name_context_md5'],
						$args['name'],
						$args['value'],
						$args['status'],
						$args['translation_priority']
					)
				);
			} else {
				$wpdb->query(
					$wpdb->prepare(
						"INSERT IGNORE INTO {$wpdb->prefix}icl_strings
						(language, context, gettext_context, domain_name_context_md5, name, value, status)
						VALUES (%s, %s, %s, %s, %s, %s, %d)",
						$args['language'],
						$args['context'],
						$args['gettext_context'],
						$args['domain_name_context_md5'],
						$args['name'],
						$args['value'],
						$args['status']
					)
				);
			}

			$string_id = $this->wpdb->insert_id;

			if ( $string_id === 0 ) {
				if ( empty( $this->wpdb->last_error ) ) {
					$string_id = $this->get_string_id_registered_in_concurrent_request( $args );
				} else {
					$input_args                      = $args;
					$input_args['allow_empty_value'] = $allow_empty_value;
					$string_id                       = $this->handle_db_error_and_resave_string( $input_args );
				}
			}

			icl_update_string_status( $string_id );

			$this->cache_registered_string(
				md5( $domain . $name . $context ),
				array(
					'id'    => $string_id,
					'value' => $value,
				)
			);

			\WPML\ST\PackageTranslation\StringRowsCache::noteInsert( $string_id, $domain, $name, $value, $context );

			$this->string_factory->clear_string_id_cache();
		} else {
			$string_id = 0;
		}

		return $string_id;
	}

	private function handle_db_error_and_resave_string( array $args ) {
		if ( $this->hasTextTheTableCannotStore( $args ) ) {
			return 0;
		}

		$repair_schema = new WPML_ST_Repair_Strings_Schema( wpml_get_admin_notices(), $args, $this->wpdb->last_error );

		if ( false !== strpos( $this->wpdb->last_error, 'translation_priority' ) ) {
			$repair_schema->set_command( new WPML_ST_Upgrade_DB_Strings_Add_Translation_Priority_Field( $this->wpdb ) );
		}

		if ( $repair_schema->run() ) {
			$string_id = $this->save_string(
				$args['value'],
				$args['allow_empty_value'],
				$args['language'],
				$args['context'],
				$args['gettext_context'],
				$args['name']
			);
		} else {
			$string_id                = 0;
			$this->block_save_strings = true;
		}

		return $string_id;
	}

	private function hasTextTheTableCannotStore( array $args ) {
		foreach ( self::TEXT_COLUMNS as $column ) {
			$storable = $this->wpdb->strip_invalid_text_for_column( $this->wpdb->prefix . 'icl_strings', $column, $args[ $column ] );

			if ( is_wp_error( $storable ) || $storable !== $args[ $column ] ) {
				return true;
			}
		}

		return false;
	}

	private function get_string_id_registered_in_concurrent_request( array $args ) {
		$wpdb = $this->wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}icl_strings WHERE domain_name_context_md5 = %s",
				md5( $args['context'] . $args['name'] . $args['gettext_context'] )
			)
		);
	}

	protected function initialize_current_string( $name, $context ) {
		list ( $this->domain, $this->gettext_context ) = wpml_st_extract_context_parameters( $context );

		list( $this->name, $this->domain ) = array_map(
			array(
				$this,
				'truncate_long_string',
			),
			array( $name, $this->domain )
		);

		$this->name_and_gettext_context = $this->name . $this->gettext_context;
		$this->key                      = md5( $this->domain . $this->name_and_gettext_context );
	}

	protected function truncate_name_and_context( $name, $context ) {
		if ( is_array( $context ) ) {
			$domain          = isset( $context['domain'] ) ? $context['domain'] : '';
			$gettext_context = isset( $context['context'] ) ? $context['context'] : '';
		} else {
			$domain          = $context;
			$gettext_context = '';
		}
		list( $name, $domain ) = array_map(
			array(
				$this,
				'truncate_long_string',
			),
			array( $name, $domain )
		);

		return array( $name . $gettext_context, $domain );
	}

	protected function key_by_name_and_context( $name, $context ) {

		return array(
			$this->domain,
			$this->gettext_context,
			md5( $this->domain . $this->name_and_gettext_context ),
		);
	}

	private function get_save_strings() {
		if ( null === $this->save_strings ) {
			$this->save_strings = new WPML_Autoregister_Save_Strings( $this->wpdb, $this->sitepress );
		}

		return $this->save_strings;
	}
}
