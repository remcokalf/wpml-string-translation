<?php

use WPML\Collect\Support\Collection;
use WPML\Convert\Ids;
use WPML\FP\Either;
use \WPML\FP\Obj;
use \WPML\ST\AdminTexts\TranslateNestedIds;
use function WPML\Container\make;
use function \WPML\FP\partial;
use function \WPML\FP\invoke;
use function \WPML\FP\flip;

class WPML_Admin_Texts extends WPML_Admin_Text_Functionality {
	const DOMAIN_NAME_PREFIX = 'admin_texts_';

	private $cache = [];

	private $cache_with_ids = [];

	private $option_names;

	private $option_names_with_ids;

	private $tm_instance;

	private $st_instance;

	private $translate_nested_ids;

	private $lock = false;

	private $stored_before_update = [];

	public function __construct( &$tm_instance, &$st_instance ) {
		add_action( 'plugins_loaded', [ $this, 'icl_st_set_admin_options_filters' ], 10 );
		add_action( 'plugins_loaded', [ $this, 'set_admin_options_ids_filters' ], 10 );
		add_filter( 'wpml_unfiltered_admin_string', flip( [ $this, 'get_option_without_filtering' ] ), 10, 2 );
		add_action( 'wpml_st_force_translate_admin_options', [ $this, 'force_translate_admin_options' ] );
		add_action( 'wpml_language_has_switched', [ $this, 'clear_language_caches' ] );
		$this->tm_instance = &$tm_instance;
		$this->st_instance = &$st_instance;
		global $sitepress;
		$this->translate_nested_ids = new TranslateNestedIds( $sitepress );
	}

	private static function object_to_array( $value ) {
		return is_object( $value ) ? object_to_array( $value ) : $value;
	}

	public function icl_register_admin_options( $array, $key = '', $option = array() ) {
		$option = self::object_to_array( $option );
		foreach ( $array as $k => $v ) {
			if ( '' === $key && $this->is_blacklisted( $k ) ) {
				continue;
			}

			$option = $key === '' ? array( $k => maybe_unserialize( $this->get_option_without_filtering( $k ) ) ) : $option;
			if ( is_array( $v ) ) {
				$this->icl_register_admin_options( $v, $key . '[' . $k . ']', $option[ $k ] );
			} else {
				$context  = $this->get_context( $key, $k );
				$opt_keys = self::findKeys( (string) $key );

				if ( $v === '' ) {
					icl_unregister_string( $context, $key . $k );
				} elseif ( isset( $option[ $k ] ) && ( $key === '' || $opt_keys ) ) {
					icl_register_string( $context, $key . $k, $option[ $k ], true );
					$vals     = array( $k => 1 );
					$opt_keys = array_reverse( $opt_keys );
					foreach ( $opt_keys as $opt ) {
						$vals = array( $opt => $vals );
					}

					update_option(
						self::TRANSLATABLE_NAMES_SETTING,
						array_replace_recursive( (array) get_option( self::TRANSLATABLE_NAMES_SETTING ), $vals ),
						'no'
					);
					$this->clearOptionNames();
				}
			}
		}
	}

	public function getModelForRender() {
		return $this->getModel( $this->getOptions() );
	}

	public function getModel( Collection $options ) {
		$stringNamesPerContext = $this->getStringNamesPerContext();

		$isRegistered = function ( $context, $name ) use ( $stringNamesPerContext ) {
			return $stringNamesPerContext->has( $context ) &&
				   $stringNamesPerContext->get( $context )->contains( $name );
		};

		$getItems = partial( [ $this, 'getItemModel' ], $isRegistered );

		return $options->map( $getItems )
					   ->filter()
					   ->values()
					   ->reduce( [ $this, 'flattenModelItems' ], wpml_collect() );
	}


	public function flattenModelItems( Collection $flattened, array $item ) {
		if ( empty( $item ) ) {
			return $flattened;
		}

		if ( isset( $item['items'] ) ) {
			return $flattened->merge(
				$item['items']->reduce( [ $this, 'flattenModelItems' ], wpml_collect() )
			);
		}

		return $flattened->push( $item );
	}

	public function getItemModel( callable $isRegistered, $value, $name, $key = '', $stack = [] ) {
		$sub_key = $this->getSubKey( $key, $name );

		$result = [];

		if ( $this->isMultiValue( $value ) ) {
			$stack[] = $value;

			$getSubItem      = function ( $v, $key ) use ( $isRegistered, $sub_key, $stack ) {
				return $this->getItemModel( $isRegistered, $v, $key, $sub_key, $stack );
			};
			$result['items'] = wpml_collect( $value )
				->reject( $this->isOnStack( $stack ) )
				->map( $getSubItem );
		} elseif ( is_string( $value ) || is_numeric( $value ) ) {
			$context    = $this->get_context( $key, $name );
			$stringName = $this->getDBStringName( $key, $name );

			$result['value']           = $value;
			$result['fixed']           = $this->is_sub_key_fixed( $sub_key );
			$result['name']            = $sub_key;
			$result['registered']      = $isRegistered( $context, $stringName );
			$result['hasTranslations'] = ! $result['fixed'] && $result['registered']
										 && icl_st_string_has_translations( $context, $stringName );
		}

		return $result;

	}

	private function isOnStack( array $stack ) {
		return function ( $item ) use ( $stack ) {
			return \wpml_collect( $stack )->first(
				function ( $currentItem ) use ( $item ) {
					return $currentItem === $item;
				}
			) !== null;
		};
	}

	private function is_sub_key_fixed( $sub_key ) {
		$fixed = false;
		$keys  = self::findKeys( $sub_key );

		if ( $keys ) {
			$fixed          = true;
			$fixed_settings = $this->tm_instance->admin_texts_to_translate;
			foreach ( $keys as $m ) {
				$fixed = isset( $fixed_settings[ $m ] );

				if ( $fixed ) {
					$fixed_settings = $fixed_settings[ $m ];
				} else {
					break;
				}
			}
		}

		return $fixed;
	}

	private function get_context( $option_key, $option_name ) {
		$keys = self::findKeys( (string) $option_key );

		return self::DOMAIN_NAME_PREFIX . ( $keys ? reset( $keys ) : $option_name );
	}

	public function getOptions() {
		return wpml_collect( wp_load_alloptions() )
			->reject( flip( [ $this, 'is_blacklisted' ] ) )
			->map( 'maybe_unserialize' );
	}

	private function shouldFilterOption( $optionKey ) {
		global $wp_customize;
		if ( $wp_customize instanceof \WP_Customize_Manager ) {
			return false;
		}

		if ( apply_filters( 'wpml_skip_admin_options_filters', false, $optionKey ) ) {
			return false;
		}

		if ( ! is_admin() ) {
			return true;
		}

		if ( wpml_is_ajax() ) {
			return true;
		}

		return false;
	}

	public function icl_st_set_admin_options_filters() {
		$option_names = $this->getOptionNames();

		foreach ( $option_names as $option_key => $option ) {
			if ( $this->is_blacklisted( $option_key ) ) {
				unset( $option_names[ $option_key ] );
				update_option( self::TRANSLATABLE_NAMES_SETTING, $option_names, 'no' );
				continue;
			}
			if ( 'theme' === $option_key || 'plugin' === $option_key ) {
				continue;
			}
			if ( $this->shouldFilterOption( $option_key ) ) {
				$this->add_filter_for( $option_key );
			}
			add_action( 'update_option_' . $option_key, array( $this, 'on_update_original_value' ), 10, 3 );
		}
	}

	public function set_admin_options_ids_filters() {
		$option_names = $this->getOptionNamesWithIds();

		foreach ( $option_names as $option_key => $option_settings ) {
			if ( $this->is_blacklisted( $option_key ) ) {
				unset( $option_names[ $option_key ] );
				update_option( self::TRANSLATABLE_ID_NAMES_SETTING, $option_names, 'no' );
				continue;
			}
			if ( 'theme' === $option_key || 'plugin' === $option_key ) {
				continue;
			}
			if ( $this->shouldFilterOption( $option_key ) ) {
				$this->add_ids_filter_for( $option_key );
			}
		}
	}

	public function force_translate_admin_options( $options ) {
		wpml_collect( $options )->each( [ $this, 'add_filter_for' ] );
	}

	public function add_filter_for( $option ) {
		add_filter( 'option_' . $option, [ $this, 'icl_st_translate_admin_string' ] );
		add_filter( 'pre_update_option_' . $option, [ $this, 'keepUntouchedValues' ], 10, 3 );
	}

	public function keepUntouchedValues( $value, $old_value, $option ) {
		$stored = maybe_unserialize( $this->get_option_without_filtering( $option ) );
		$value  = $this->restoreUntouched( $value, $old_value, $stored );

		if ( $value === $stored ) {
			return $old_value;
		}

		$this->stored_before_update[ $option ] = $stored;

		return $value;
	}

	private function restoreUntouched( $value, $read, $stored ) {
		if ( is_array( $value ) && is_array( $read ) && is_array( $stored ) ) {
			foreach ( $value as $key => $item ) {
				if ( array_key_exists( $key, $read ) && array_key_exists( $key, $stored ) ) {
					$value[ $key ] = $this->restoreUntouched( $item, $read[ $key ], $stored[ $key ] );
				}
			}

			return $value;
		}

		return $value === $read ? $stored : $value;
	}

	public function add_ids_filter_for( $option ) {
		add_filter( 'option_' . $option, [ $this, 'translate_ids_in_admin_string' ], 11, 2 );
	}

	public function icl_st_translate_admin_string( $option_value, $key = '', $name = '', $root_level = true, $lang = null ) {
		if ( $root_level && $this->lock ) {
			return $option_value;
		}

		$this->lock = true;

		$option_name = substr( current_filter(), 7 );
		$name        = $name === '' ? $option_name : $name;
		$lang        = $lang ?: $this->st_instance->get_current_string_language( $name );
		$blog_id     = get_current_blog_id();

		if ( isset( $this->cache[ $blog_id ][ $lang ][ $name ] ) ) {
			$this->lock = false;

			return $this->cache[ $blog_id ][ $lang ][ $name ];
		}

		$is_serialized = is_serialized( $option_value );
		$option_value  = $is_serialized ? unserialize( $option_value ) : $option_value;

		if ( is_array( $option_value ) || is_object( $option_value ) ) {
			$option_value = $this->translate_multiple( $option_value, $key, $name, $lang );
		} else {
			$option_value = $this->translate_single( $option_value, $key, $name, $option_name, $lang );
		}

		$option_value = $is_serialized ? serialize( $option_value ) : $option_value;

		if ( $root_level ) {
			$this->lock                                = false;
			$this->cache[ $blog_id ][ $lang ][ $name ] = $option_value;
		}

		return $option_value;
	}

	public function translate_ids_in_admin_string( $option_value, $option_name ) {
		$option_names = $this->getOptionNamesWithIds();
		if ( ! array_key_exists( $option_name, $option_names ) ) {
			return $option_value;
		}

		$blog_id = get_current_blog_id();

		if ( isset( $this->cache_with_ids[ $blog_id ][ $option_name ] ) ) {
			return $this->cache_with_ids[ $blog_id ][ $option_name ];
		}

		$is_serialized = is_serialized( $option_value );
		$option_value  = $is_serialized ? unserialize( $option_value ) : $option_value;
		$option_paths  = $option_names[ $option_name ];

		foreach ( $option_paths as $path_and_object_data ) {
			$type         = Obj::propOr( TranslateNestedIds::TYPE_POST_IDS, 'type', $path_and_object_data );
			$slug         = Obj::propOr( Ids::ANY_POST, 'slug', $path_and_object_data );
			$path         = explode( '>', Obj::propOr( '', 'path', $path_and_object_data ) );
			$option_value = $this->translate_nested_ids->convertByPath( $option_value, $path, $type, $slug );
		}

		$option_value = $is_serialized ? serialize( $option_value ) : $option_value;

		$this->cache_with_ids[ $blog_id ][ $option_name ] = $option_value;

		return $option_value;
	}

	private function isAdminText( $key, $name ) {

		return null !== Either::of( $this->getSubKey( $key, $name ) )
							  ->map( [ self::class, 'getKeysParts' ] )
							  ->tryCatch( invoke( 'reduce' )->with( flip( Obj::prop() ), $this->getOptionNames() ) )
							  ->getOrElse( null );
	}

	public static function getKeysParts( $option ) {
		return wpml_collect( self::findKeys( $option ) );
	}

	private static function findKeys( $string ) {
		return array_filter( explode( '][', preg_replace( '/^\[(.*)\]$/', '$1', $string ) ), 'strlen' );
	}

	public function clear_language_caches() {
		$this->cache          = [];
		$this->cache_with_ids = [];
	}

	public function clear_cache_for_option( $option_name ) {
		$blog_id = get_current_blog_id();
		if ( isset( $this->cache[ $blog_id ] ) ) {
			foreach ( $this->cache[ $blog_id ] as $lang_code => &$cache_data ) {
				if ( array_key_exists( $option_name, $cache_data ) ) {
					unset( $cache_data[ $option_name ] );
				}
			}
		}
	}

	public function on_update_original_value( $old_value, $value, $option_name, $name = '', $sub_key = '' ) {
		if ( '' === $sub_key && array_key_exists( $option_name, $this->stored_before_update ) ) {
			$old_value = $this->stored_before_update[ $option_name ];
			unset( $this->stored_before_update[ $option_name ] );
		}

		$name = $name ? $name : $option_name;

		$value     = self::object_to_array( $value );
		$old_value = self::object_to_array( $old_value );

		if ( is_array( $value ) ) {
			foreach ( array_keys( $value ) as $key ) {
				$this->on_update_original_value(
					isset( $old_value[ $key ] ) ? $old_value[ $key ] : '',
					$value[ $key ],
					$option_name,
					$key,
					$this->getSubKey( $sub_key, $name )
				);
			}
		} else {
			if ( $this->isAdminText( $sub_key, $name ) ) {
				icl_st_update_string_actions( self::DOMAIN_NAME_PREFIX . $option_name, $this->getDBStringName( $sub_key, $name ), $old_value, $value );
			}
		}

		if ( $sub_key === '' ) {
			$this->clear_cache_for_option( $option_name );
		}
	}

	public function migrate_original_values() {
		$migrate = function ( $option_name ) {
			$option_value = maybe_unserialize( $this->get_option_without_filtering( $option_name ) );
			$this->on_update_original_value( '', $option_value, $option_name );
		};
		wpml_collect( $this->getOptionNames() )
			->keys()
			->filter()
			->each( $migrate );
	}

	public static function get_migrator() {
		return function () {
			wpml_st_load_admin_texts()->migrate_original_values();
		};
	}

	private function translate_multiple( $option_value, $key, $name, $lang = null ) {
		$subKey = $this->getSubKey( $key, $name );

		foreach ( $option_value as $k => &$value ) {
			$value = $this->icl_st_translate_admin_string(
				$value,
				$subKey,
				$k,
				false,
				$lang
			);
		}

		return $option_value;
	}

	private function translate_single( $option_value, $key, $name, $option_name, $lang = null ) {
		if ( $option_value !== '' && $this->isAdminText( $key, $name ) ) {
			$has_translation = null;
			$option_value    = icl_translate(
				self::DOMAIN_NAME_PREFIX . $option_name,
				$key . $name,
				$option_value,
				false,
				$has_translation,
				$lang
			);
		}

		return $option_value;
	}

	private function getOptionNames() {
		if ( null === $this->option_names ) {
			$this->option_names = get_option( self::TRANSLATABLE_NAMES_SETTING );
			if ( ! is_array( $this->option_names ) ) {
				$this->option_names = [];
			}
		}

		return $this->option_names;
	}

	private function clearOptionNames() {
		$this->option_names = null;
	}

	private function getOptionNamesWithIds() {
		if ( null === $this->option_names_with_ids ) {
			$this->option_names_with_ids = get_option( self::TRANSLATABLE_ID_NAMES_SETTING, [] );
			if ( ! is_array( $this->option_names_with_ids ) ) {
				$this->option_names_with_ids = [];
			}
		}

		return $this->option_names_with_ids;
	}

	private function getSubKey( $key, $name ) {
		return $key . '[' . $name . ']';
	}

	private function getDBStringName( $key, $name ) {
		return $key . $name;
	}

	private function getStringNamesPerContext() {
		$strings = make( WPML_ST_DB_Mappers_Strings::class )
			->get_all_by_context( self::DOMAIN_NAME_PREFIX . '%' );

		return wpml_collect( $strings )
			->groupBy( 'context' )
			->map( invoke( 'pluck' )->with( 'name' ) );
	}

	private function isMultiValue( $value ) {
		return is_array( $value ) ||
			   ( is_object( $value ) && '__PHP_Incomplete_Class' !== get_class( $value ) );
	}
}
