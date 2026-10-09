<?php
require_once dirname( __FILE__ ) . '/wpml-admin-text-configuration.php';
require_once dirname( __FILE__ ) . '/wpml-admin-text-functionality.class.php';
require_once dirname( __FILE__ ) . '/wpml-admin-text-config-sync.class.php';

use WPML\Convert\Ids;
use WPML\FP\Obj;
use WPML\ST\AdminTexts\TranslateNestedIds;

class WPML_Admin_Text_Import extends WPML_Admin_Text_Functionality {

	private $st_records;

	private $wp_api;

	private $translatable_ids = [];

	private $configured_option_names = [];

	function __construct( WPML_ST_Records $st_records, WPML_WP_API $wp_api ) {
		$this->st_records = $st_records;
		$this->wp_api     = $wp_api;
	}

	function parse_config( array $admin_texts, $config_handler_hash ) {
		$admin_texts_hash = md5( serialize( $admin_texts ) );
		$transient_name   = 'wpml_admin_text_import:parse_config:' . $config_handler_hash;
		$arr               = array();
		$arr_context       = array();
		$arr_type          = array();

		$this->translatable_ids        = array();
		$this->configured_option_names = array();

		foreach ( $admin_texts as $a ) {
			$type               = isset( $a['type'] ) ? $a['type'] : 'plugin';
			$admin_text_context = isset( $a['context'] ) ? $a['context'] : '';
			$admin_string_name  = $a['attr']['name'];
			if ( $this->is_blacklisted( $admin_string_name ) ) {
				continue;
			}
			if ( $this->has_translatable_ids( $a ) ) {
				$key_type = $this->get_translatable_ids_type( $a );
				$key_slug = $this->get_translatable_ids_slug( $a, $key_type );
				$key_path = '';
				$this->register_translatable_id( $admin_string_name, $key_type, $key_slug, $key_path );
				continue;
			}
			if ( ! empty( $a['key'] ) ) {
				foreach ( $a['key'] as $key ) {
					$key_name = $key['attr']['name'];
					if ( $this->has_translatable_ids( $key ) ) {
						$key_type = $this->get_translatable_ids_type( $key );
						$key_slug = $this->get_translatable_ids_slug( $key, $key_type );
						$key_path = $key_name;
						$this->register_translatable_id( $admin_string_name, $key_type, $key_slug, $key_path );
						continue;
					}
					if ( $this->hasNestedKeys( $key ) ) {
						$nested_names = $this->read_admin_texts_recursive(
							$key['key'],
							$admin_text_context,
							$type,
							$arr_context,
							$arr_type,
							$admin_string_name,
							$key_name
						);
						if ( false !== $nested_names ) {
							$arr[ $admin_string_name ][ $key_name ] = $nested_names;
						}
					} else {
						$arr[ $admin_string_name ][ $key_name ] = 1;
					}
					$arr_context[ $admin_string_name ]      = $admin_text_context;
					$arr_type[ $admin_string_name ]         = $type;
				}
				$arr_context[ $admin_string_name ] = $admin_text_context;
				$arr_type[ $admin_string_name ]    = $type;
			} else {
				$arr[ $admin_string_name ]         = 1;
				$arr_context[ $admin_string_name ] = $admin_text_context;
				$arr_type[ $admin_string_name ]    = $type;
			}
		}

		$this->configured_option_names = $arr;

		global $iclTranslationManagement;
		if ( isset( $iclTranslationManagement ) && isset( $iclTranslationManagement->admin_texts_to_translate ) ) {
			$iclTranslationManagement->admin_texts_to_translate = WPML_Admin_Text_Config_Sync::merge_names(
				$iclTranslationManagement->admin_texts_to_translate,
				$arr
			);
		}

		if ( $this->wp_api->is_string_translation_page() || get_transient( $transient_name ) !== $admin_texts_hash ) {
			global $iclTranslationManagement, $sitepress;

			$_icl_admin_option_names = get_option( self::TRANSLATABLE_NAMES_SETTING );

			$arr_options = array();
			if ( ! empty( $arr ) ) {
				foreach ( $arr as $key => $v ) {
					$value = maybe_unserialize( (string) $this->get_option_without_filtering( (string) $key ) );
					$value = is_array( $value ) && is_array( $v ) ? array_intersect_key( $value, $v ) : $value;
					$admin_text_context = isset( $arr_context[ $key ] ) ? $arr_context[ $key ] : '';
					$type               = isset( $arr_type[ $key ] ) ? $arr_type[ $key ] : '';

					$req_upgrade = ! $sitepress->get_setting( 'admin_text_3_2_migration_complete_' . $admin_texts_hash, false );
					if ( (bool) $value === true ) {
						$this->register_string_recursive( $key,
						                                  $value,
						                                  $arr[ $key ],
						                                  '',
						                                  $key,
						                                  $req_upgrade,
						                                  $type,
						                                  $admin_text_context );
					}
					$arr_options[ $key ] = $v;
				}

				$_icl_admin_option_names = is_array( $_icl_admin_option_names )
					? array_replace_recursive( $_icl_admin_option_names, $arr_options ) : $arr_options;
			}

			update_option( self::TRANSLATABLE_NAMES_SETTING, $_icl_admin_option_names, 'no' );
			$this->save_translatable_ids();

			set_transient( $transient_name, $admin_texts_hash );
			$sitepress->set_setting( 'admin_text_3_2_migration_complete_' . $admin_texts_hash, true, true );
		}
	}

	public function get_configured_option_names() {
		return $this->configured_option_names;
	}

	public function get_configured_translatable_id_names() {
		return $this->translatable_ids;
	}

	protected function read_admin_texts_recursive( $keys, $admin_text_context, $admin_string_type, &$arr_context, &$arr_type, $admin_string_name = '', $path = '' ) {
		$keys = ! empty( $keys ) && isset( $keys ['attr']['name'] ) ? array( $keys ) : $keys;
		foreach ( $keys as $key ) {
			$key_name = $key['attr']['name'];
			if ( $this->has_translatable_ids( $key ) ) {
				$key_type = $this->get_translatable_ids_type( $key );
				$key_slug = $this->get_translatable_ids_slug( $key, $key_type );
				$key_path = $path . '>' . $key_name;
				$this->register_translatable_id( $admin_string_name, $key_type, $key_slug, $key_path );
				continue;
			}
			if ( $this->hasNestedKeys( $key ) ) {
				$nested_names = $this->read_admin_texts_recursive(
					$key['key'],
					$admin_text_context,
					$admin_string_type,
					$arr_context,
					$arr_type,
					$admin_string_name,
					$path . '>' . $key_name
				);
				if ( false !== $nested_names ) {
					$arr[ $key_name ] = $nested_names;
				}
			} else {
				$arr[ $key_name ]         = 1;
				$arr_context[ $key_name ] = $admin_text_context;
				$arr_type[ $key_name ]    = $admin_string_type;
			}
		}

		return isset( $arr ) ? $arr : false;
	}

	private function hasNestedKeys( $key ) {
		return ! empty( $key['key'] );
	}

	private function has_translatable_ids( $entry ) {
		$entry = Obj::path( [ 'attr', 'type' ], $entry );
		return in_array( $entry, [ TranslateNestedIds::TYPE_POST_IDS, TranslateNestedIds::TYPE_TAXONOMY_IDS ], true );
	}

	private function get_translatable_ids_type( $entry ) {
		return Obj::pathOr( TranslateNestedIds::TYPE_POST_IDS, [ 'attr', 'type' ], $entry );
	}

	private function get_translatable_ids_slug( $entry, $type ) {
		return Obj::path( [ 'attr', 'sub-type' ], $entry ) ?: wpml_collect( [
			TranslateNestedIds::TYPE_POST_IDS     => Ids::ANY_POST,
			TranslateNestedIds::TYPE_TAXONOMY_IDS => Ids::ANY_TERM,
		] )->get( $type, Ids::ANY_POST );
	}

	private function register_translatable_id( $setting_name, $type, $slug, $path = '' ) {
		if ( ! array_key_exists( $setting_name, $this->translatable_ids ) ) {
			$this->translatable_ids[ $setting_name ] = [];
		}
		$this->translatable_ids[ $setting_name ][ $path ] = [
			'type' => $type,
			'slug' => $slug,
			'path' => $path,
		];
	}

	private function save_translatable_ids() {
		$stored = get_option( self::TRANSLATABLE_ID_NAMES_SETTING, array() );
		$stored = is_array( $stored ) ? $stored : array();

		update_option(
			self::TRANSLATABLE_ID_NAMES_SETTING,
			array_replace_recursive( $stored, $this->translatable_ids ),
			'no'
		);
	}

	private function register_string_recursive( $key, $value, $arr, $prefix, $suffix, $requires_upgrade, $type, $admin_text_context_old ) {
		if ( is_scalar( $value ) ) {
			icl_register_string( WPML_Admin_Texts::DOMAIN_NAME_PREFIX . $suffix, $prefix . $key, $value, true );
			if ( $requires_upgrade ) {
				$this->migrate_3_2( $type, $admin_text_context_old, $suffix, $prefix . $key );
			}
		} elseif ( ! is_null( $value ) ) {
			foreach ( $value as $sub_key => $sub_value ) {
				if ( isset( $arr[ $sub_key ] ) ) {
					$this->register_string_recursive( $sub_key,
					                                  $sub_value,
					                                  $arr[ $sub_key ],
					                                  $prefix . '[' . $key . ']',
					                                  $suffix,
					                                  $requires_upgrade,
					                                  $type,
					                                  $admin_text_context_old );
				}
			}
		}
	}

	private function migrate_3_2( $type, $old_admin_text_context, $new_admin_text_context, $key ) {
		global $wpdb;

		$old_string_id = icl_st_is_registered_string( WPML_Admin_Texts::DOMAIN_NAME_PREFIX . $type . '_' . $old_admin_text_context, $key );
		if ( $old_string_id ) {
			$new_string_id = icl_st_is_registered_string( WPML_Admin_Texts::DOMAIN_NAME_PREFIX . $new_admin_text_context, $key );
			if ( $new_string_id ) {
				$wpdb->update( $wpdb->prefix . 'icl_string_translations', array( 'string_id' => $new_string_id ), array( 'string_id' => $old_string_id ) );
				$this->st_records->icl_strings_by_string_id( $new_string_id )
				                 ->update(
					                 array(
						                 'status' => $this->st_records
							                 ->icl_strings_by_string_id( $old_string_id )
							                 ->status()
					                 )
				                 );
			}
		}
	}
}
