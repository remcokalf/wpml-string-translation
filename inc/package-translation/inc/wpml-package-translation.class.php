<?php

class WPML_Package_Translation extends WPML_Package_Helper {
	var $load_priority = 100;
	var $package_translation_active;
	var $admin_lang_switcher = null;

	function __construct() {
		parent::__construct();
		add_action( 'wpml_loaded', array( $this, 'loaded' ), $this->load_priority, 1 );
	}

	public function loaded( ?SitePress $sitepress = null ) {
		parent::loaded();

		if ( null === $sitepress ) {
			global $sitepress;
		}

		$is_setup_complete = (bool) $sitepress->get_setting( 'setup_complete', false );

		if ( $is_setup_complete ) {
			$this->run_db_update();
		}

		if ( $this->passed_dependencies() && $is_setup_complete ) {
			$this->add_admin_hooks();
			$this->add_global_hooks();

			if ( is_admin() ) {

				if ( $this->is_refresh_required() ) {
					add_action( 'init', array( $this, 'refresh_packages' ), 999, 0 );
					$this->set_refresh_not_required();
				}
			}
		}
	}

	private function add_admin_hooks() {
		if ( is_admin() || $this->is_doing_xmlrpc() || wpml_is_rest_request() || ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {
			\WPML\Request\Adapter\Ajax::register( 'wpml_delete_packages', \WPML\Request\Policy\Policy::capability( [ 'wpml_manage_string_translation', 'manage_translations' ], \WPML\Request\Policy\Authenticity::actionNonce( 'wpml_package_nonce', 'wpnonce' ) ), array( $this, 'delete_packages_ajax' ) );
			\WPML\Request\Adapter\Ajax::register( 'wpml_change_package_lang', \WPML\Request\Policy\Policy::capability( [ 'wpml_manage_string_translation', 'manage_translations' ], \WPML\Request\Policy\Authenticity::actionNonce( 'wpml_package_nonce', 'wpnonce' ) ), array( $this, 'change_package_lang_ajax' ) );

			add_filter( 'wpml_pt_all_packages', array( $this, 'get_all_packages' ) );

			add_filter( 'wpml_tm_element_type', array( $this, 'get_element_type' ), 10, 2 );
			add_filter( 'wpml_tm_dashboard_title_locations', array( $this, 'add_title_db_location' ), 10, 1 );

			add_filter( 'wpml_string_title_from_id', array( $this, 'string_title_from_id_filter' ), 10, 2 );
			add_filter( 'WPML_register_string', array( $this, 'register_string_for_translation' ), 10, 5 );

			add_action( 'wpml_add_string_translation', array( $this, 'add_string_translation_action' ), 10, 7 );

			add_filter( 'WPML_get_translated_strings', array( $this, 'get_translated_strings' ), 10, 2 );
			add_action( 'WPML_set_translated_strings', array( $this, 'set_translated_strings' ), 10, 2 );
			add_action( 'WPML_show_package_language_ui', array( $this, 'show_language_selector' ), 10, 2 );

			add_filter( 'wpml_get_translated_strings', array( $this, 'get_translated_strings' ), 10, 2 );
			add_action( 'wpml_set_translated_strings', array( $this, 'set_translated_strings' ), 10, 2 );
			add_action( 'wpml_show_package_language_ui', array( $this, 'show_language_selector' ), 10, 2 );
			add_action( 'wpml_show_package_language_admin_bar', array( $this, 'show_admin_bar_language_selector' ), 10, 2 );

			add_filter( 'wpml_active_string_package_kinds', array( $this, 'get_active_string_package_kinds' ) );
			add_filter( 'wpml_get_translatable_types', array( $this, 'get_translatable_types' ), 10, 1 );
			add_filter( 'wpml_get_translatable_item', array( $this, 'get_translatable_item' ), 10, 3 );
			add_filter( 'wpml_external_item_url', array( $this, 'get_package_edit_url' ), 10, 2 );
			add_filter( 'wpml_external_item_link', array( $this, 'get_package_edit_link' ), 10, 3 );
			add_filter( 'wpml_get_external_item_title', array( $this, 'get_package_title' ), 10, 3 );
			add_filter( 'wpml_element_id_from_package', array( $this, 'get_element_id_from_package_filter' ), 10, 2 );
			add_filter( 'wpml_get_package_type', array( $this, 'get_package_type' ), 10, 2 );
			add_filter( 'wpml_get_package_type_prefix', array( $this, 'get_package_type_prefix' ), 10, 2 );
			add_filter( 'wpml_language_for_element', array( $this, 'get_language_for_element' ), 10, 2 );
			add_action( 'wpml_save_external', [ $this, 'save_package_translations' ], 10, 3 );

			add_filter( 'wpml_tm_external_translation_job_title', array( $this, 'get_post_title' ), 10, 2 );

			add_filter( 'wpml_tm_dashboard_sql', array( $this, 'tm_dashboard_sql_filter' ), 10, 1 );

			add_filter( 'wpml_tm_editor_string_name', array( $this, 'get_editor_string_name' ), 10, 2 );
			add_filter( 'wpml_tm_editor_string_style', array( $this, 'get_editor_string_style' ), 10, 3 );

			add_action( 'wpml_delete_package_action', array( $this, 'delete_package_action' ), 10, 2 );

			add_action( 'deleted_post', array( $this, 'remove_post_packages' ) );
		}
	}

	private function is_doing_xmlrpc() {
		return ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST );
	}

	private function add_global_hooks() {

		add_filter( 'WPML_translate_string', array( $this, 'translate_string' ), 10, 3 );
		add_filter( 'wpml_translate_string', array( $this, 'translate_string' ), 10, 3 );

		add_action( 'wpml_register_string', array( $this, 'register_string_action' ), 10, 5 );
		add_action( 'wpml_start_string_package_registration', array( $this, 'start_string_package_registration_action' ), 10, 1 );
		add_action( 'wpml_delete_unused_package_strings', array( $this, 'delete_unused_package_strings_action' ), 10, 1 );
		add_action( 'wpml_delete_package', array( $this, 'delete_package_action' ), 10, 2 );
		add_filter( 'wpml_st_get_post_string_packages', array( $this, 'get_post_string_packages' ), 10, 2 );
		add_filter( 'wpml_st_get_string_package', array( $this, 'get_string_package' ), 10, 2 );
		add_filter( 'wpml_string_id_from_package', array( $this, 'string_id_from_package_filter' ), 10, 4 );

		add_filter( 'wpml_is_external', array( $this, 'is_external' ), 10, 2 );
	}

	private function run_db_update() {
		if ( is_admin() ) {
			WPML_Package_Translation_Schema::run_update();
		}
	}

	private function passed_dependencies() {
		return defined( 'ICL_SITEPRESS_VERSION' )
			   && defined( 'WPML_ST_VERSION' )
			   && defined( 'WPML_TM_VERSION' );
	}

	public function add_title_db_location( $locations ) {
		global $wpdb;

		$locations[ $wpdb->prefix . 'icl_string_packages' ] = array(
			'id_column'           => 'ID',
			'title_column'        => 'title',
			'element_type_prefix' => 'package',
			'date_column'         => false,
		);

		return $locations;
	}

	function get_package_edit_url( $url, $post_id ) {
		$package = new WPML_Package( $post_id );
		if ( ! $package ) {
			return false;
		}
		if ( isset( $package->edit_link ) ) {
			$url = $package->edit_link;
		}

		return $url;
	}

	public function get_package_title( $title, $kind, $id ) {
		$package = new WPML_Package( $id );
		if ( ! $package ) {
			return $title;
		}
		if ( isset( $package->title ) ) {
			$title = $package->title;
		}

		return $title;
	}

	function get_package_view_link( $link, $post_id, $hide_if_missing_link = false ) {
		$package = new WPML_Package( $post_id );
		if ( ! $package ) {
			return $link;
		}
		return $this->build_package_link( $package->view_link, $package->title, $hide_if_missing_link );
	}

	function get_package_edit_link( $link, $post_id, $hide_if_missing_link = false ) {
		$package = new WPML_Package( $post_id );
		if ( ! $package ) {
			return $link;
		}
		return $this->build_package_link( $package->edit_link, esc_html( $package->title ), $hide_if_missing_link );
	}

	private function build_package_link( $url, $title, $hide_if_missing_link = false ) {
		$link = '<a href="' . $url . '">' . $title . '</a>';
		if ( false === $hide_if_missing_link ) {
			if ( ! $url ) {
				$link = $title;
			}
		} elseif ( ! $url ) {
			$link = '';
		}

		return $link;
	}

	function show_language_selector( $package, $args = array() ) {
		global $wpdb, $sitepress;

		wp_enqueue_script( 'wpml-purify' );

		$wpml_pt_meta = new WPML_Package_Translation_Metabox( $package, $wpdb, $sitepress, $args );
		echo $wpml_pt_meta->get_metabox();
	}

	function show_admin_bar_language_selector( $package, $args = array() ) {
		require WPML_PACKAGE_TRANSLATION_PATH . '/inc/wpml-package-admin-lang-switcher.class.php';
		$this->admin_lang_switcher = new WPML_Package_Admin_Lang_Switcher( $package, $args );
	}

	private function languages_to_csv( $package_target_languages ) {
		global $sitepress;

		$language_names = array();
		foreach ( $package_target_languages as $language_code => $value ) {
			$language_names[] = ucfirst( $sitepress->get_display_language_name( $language_code, $sitepress->get_admin_language() ) );
		}

		return $language_names;
	}

	function is_external( $result, $type ) {
		return $result || is_a( $type, 'WPML_Package' ) || $type == 'package';
	}

	public function get_element_type( $type, $element ) {
		if ( $this->is_a_package( $element ) ) {
			$type = 'package';
		}

		return $type;
	}

	public function attributes_to_string( $attributes ) {
		$result = '';
		foreach ( $attributes as $key => $value ) {
			if ( $result ) {
				$result .= ' ';
			}
			$result .= esc_html( $key ) . '="' . esc_attr( $value ) . '"';
		}

		return $result;
	}

	public static function get_package_element_type( $kind_slug ) {
		if ( is_object( $kind_slug ) ) {
			$kind_slug = $kind_slug->kind_slug;
		}
		if ( is_array( $kind_slug ) ) {
			$kind_slug = $kind_slug['kind_slug'];
		}

		return 'package_' . $kind_slug;
	}

	public function package_has_kind( $package ) {
		return isset( $package['kind'] ) && $package['kind'];
	}

	public function package_has_name( $package ) {
		return isset( $package['name'] ) && $package['name'];
	}

	public function package_has_title( $package ) {
		return isset( $package['title'] ) && $package['title'];
	}

	public function package_has_kind_and_name( $package ) {
		return $this->package_has_kind( $package ) && $this->package_has_name( $package );
	}

	public function sanitize_string_with_underscores( $string_name ) {
		return preg_replace( '/[ \[\]]+/', '_', $string_name );
	}

	function new_external_item( $type, $package_item, $get_string_data = false ) {
		$package_id = $package_item['ID'];

		$item                 = new stdClass();
		$item->external_type  = true;
		$item->type           = $type;
		$item->ID             = $package_id;
		$item->post_type      = $type;
		$item->post_id        = 'external_' . $item->post_type . '_' . $package_item['ID'];
		$item->post_date      = '';
		/* translators: Option in the filter on the Theme and plugins localization page: show only themes or plugins that are switched on. On the Packages page it is also the state of a package that is in use. Adjective. */
		$item->post_status    = __( 'Active', 'wpml-string-translation' );
		$item->post_title     = $package_item['title'];
		$item->is_translation = false;

		if ( $get_string_data ) {
			$item->string_data = $this->_get_package_strings( $package_item );
		}

		return $item;
	}

	function get_package_from_external_id( $post_id ) {

		global $wpdb;

		$packages = $wpdb->get_col( "SELECT ID FROM {$wpdb->prefix}icl_string_packages WHERE ID>0" );

		foreach ( $packages as $package_id ) {

			$package = new WPML_Package( $package_id );

			$test = $this->get_external_id_from_package( $package );
			if ( is_string( $post_id ) && $post_id == $test ) {
				return $package;
			}
		}

		return false;
	}

	function _get_package_strings( $package_item ) {
		global $wpdb;
		$strings = array();

		$package_item_id = $package_item['ID'];
		$results         = $wpdb->get_results( $wpdb->prepare( "SELECT name, value FROM {$wpdb->prefix}icl_strings WHERE string_package_id=%d", $package_item_id ) );

		foreach ( $results as $result ) {
			$string_name             = (bool) $package_item_id === true && strpos(
				$result->name,
				(string) $package_item_id
			) === 0
				? substr( $result->name, strlen( $package_item_id ) + 1 ) : $result->name;
			$strings[ $string_name ] = $result->value;
		}

		if ( isset( $this->registered_strings[ $package_item_id ]['strings'] ) ) {
			foreach ( $this->registered_strings[ $package_item_id ]['strings'] as $id => $string_data ) {
				$strings[ $id ] = $string_data['value'];
			}
		}

		return $strings;
	}

	function get_link( $item, $package_item, $anchor, $hide_empty ) {
		if ( $item == '' ) {
			$package_item = $this->get_package_from_external_id( $package_item );
			if ( ! $package_item ) {
				return '';
			}

			$has_link = isset( $package_item->edit_link ) && $package_item->edit_link;
			if ( false === $anchor ) {
				if ( $has_link ) {
					$anchor = '<a href="' . $package_item->edit_link . '">' . $package_item['title'] . '</a>';
				} elseif ( ! $hide_empty ) {
					$anchor = $package_item['title'];
				}
			} else {
				if ( $has_link ) {
					$anchor = '<a href="' . $package_item['edit_link'] . '">' . $anchor . '</a>';
				}
			}

			$item = $anchor;
		}

		return $item;
	}

	private function get_package_details( $package_id ) {
		global $wpdb;
		static $cache = array();

		if ( ! isset( $cache[ $package_id ] ) ) {
			$item                 = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}icl_string_packages WHERE ID=%d", $package_id ), ARRAY_A );
			$cache[ $package_id ] = $item;
		}

		return $cache[ $package_id ];
	}

	function get_string_context_title( $context, $string_details ) {
		global $wpdb;

		static $cache = array();

		if ( ! isset( $cache[ $context ] ) ) {
			$package_id = $wpdb->get_var( $wpdb->prepare( "SELECT string_package_id FROM {$wpdb->prefix}icl_strings WHERE id=%d", $string_details['string_id'] ) );
			if ( $package_id ) {
				$package_details = $this->get_package_details( $package_id );
				if ( $package_details ) {
					$cache[ $context ] = $package_details->kind . ' - ' . $package_details->title;
				} else {
					$cache[ $context ] = $context;
				}
			} else {
				$cache[ $context ] = $context;
			}
		}

		return $cache[ $context ];
	}

	function get_string_title( $title, $string_details ) {
		global $wpdb;

		$string_title = $wpdb->get_var( $wpdb->prepare( "SELECT title FROM {$wpdb->prefix}icl_strings WHERE id=%d", $string_details['string_id'] ) );
		if ( $string_title ) {
			return $string_title;
		} else {
			return $title;
		}
	}

	function _get_post_translations( $package ) {
		global $sitepress;

		if ( is_object( $package ) ) {
			$package = get_object_vars( $package );
		}

		$element_type = $this->get_package_element_type( $package['kind_slug'] );
		$trid         = $sitepress->get_element_trid( $package['ID'], $element_type );

		return $sitepress->get_element_translations( $trid, $element_type );
	}

	function _is_translation_in_progress( $package ) {
		global $wpdb;

		$post_translations = self::_get_post_translations( $package );

		foreach ( $post_translations as $lang => $translation ) {
			$res = $wpdb->get_row( $wpdb->prepare( "SELECT status, needs_update, md5 FROM {$wpdb->prefix}icl_translation_status WHERE translation_id=%d", $translation->translation_id ) );
			if ( $res && $res->status == ICL_TM_IN_PROGRESS ) {
				return true;
			}
		}

		return false;
	}

	function _delete_translation_job( $package_id ) {
		global $wpdb;

		$package = $this->get_package_details( $package_id );

		$post_translations = $this->_get_post_translations( $package );
		foreach ( $post_translations as $lang => $translation ) {
			$rid = $wpdb->get_var( $wpdb->prepare( "SELECT rid FROM {$wpdb->prefix}icl_translation_status WHERE translation_id=%d", $translation->translation_id ) );
			if ( $rid ) {
				$job_id = $wpdb->get_var( $wpdb->prepare( "SELECT job_id FROM {$wpdb->prefix}icl_translate_job WHERE rid=%d", $rid ) );

				if ( $job_id ) {
					$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}icl_translate_job WHERE job_id=%d", $job_id ) );
					$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}icl_translate WHERE job_id=%d", $job_id ) );
				}
			}
		}
	}

	public function tm_dashboard_sql_filter( $sql ) {
		global $wpdb;

		$sql .= " AND i.element_id NOT IN ( SELECT ID FROM {$wpdb->prefix}icl_string_packages WHERE post_id IS NOT NULL AND element_type = 'package_layout' )";
		return $sql;
	}

	private function is_refresh_required() {
		$refresh_required = get_option( 'wpml-package-translation-refresh-required', 'yes' );
		return 'yes' === $refresh_required || '1' === $refresh_required;
	}

	private function set_refresh_not_required() {
		update_option( 'wpml-package-translation-refresh-required', 'no', false );
	}

	public function save_package_translations( $element_type_prefix, $job, $decoder ) {
		if ( $element_type_prefix === 'package' ) {
			$element_type_prefix             = $this->get_package_type_prefix( $element_type_prefix, $job->original_doc_id );
			$needs_process_translation_files = false;

			foreach ( $job->elements as $field ) {
				if ( $field->field_translate ) {
					$field_context = apply_filters( 'wpml_save_external_package_field_context', $element_type_prefix, $field, $job );
					$string_id     = icl_st_is_registered_string( $field_context, $field->field_type );
					if ( ! $string_id ) {
						icl_register_string( $field_context, $field->field_type, $decoder( $field->field_data, $field->field_format ) );
						$string_id = icl_st_is_registered_string( $field_context, $field->field_type );
					}
					if ( $string_id ) {
						$needs_process_translation_files = true;
						icl_add_string_translation( $string_id, $job->language_code, $decoder( $field->field_data_translated, $field->field_format ), ICL_TM_COMPLETE );
					}
				}
			}

			if ( $needs_process_translation_files ) {
				do_action( 'wpml_st_translation_files_process_queue' );
			}
		}
	}

}
