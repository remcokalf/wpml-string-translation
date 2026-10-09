<?php

class WPML_Package_Translation_UI {
	var     $load_priority = 101;
	private $menu_root     = '';

	const MENU_SLUG = 'wpml-package-management';

	const SUPPORT_PAGE_SLUG     = 'sitepress-multilingual-cms/menu/support.php';
	const SUPPORT_TOOL_PACKAGES = 'packages';

	public function __construct() {
		add_action( 'wpml_loaded', array( $this, 'loaded' ), $this->load_priority );
	}

	public function loaded() {

		if ( $this->passed_dependencies() ) {
			$this->set_admin_hooks();
			do_action( 'WPML_PT_HTML' );
		}
	}

	private function passed_dependencies() {
		return defined( 'ICL_SITEPRESS_VERSION' )
			   && defined( 'WPML_ST_VERSION' )
			   && defined( 'WPML_TM_VERSION' );
	}

	private function set_admin_hooks() {
		if ( is_admin() ) {
			add_action( 'wpml_admin_menu_configure', array( $this, 'menu' ) );
			add_action( 'wpml_admin_menu_root_configured', array( $this, 'main_menu_configured' ), 10, 2 );

			add_action( 'admin_register_scripts', array( $this, 'admin_register_scripts' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		}
	}

	public function main_menu_configured( $menu_id, $root_slug ) {
		if ( 'WPML' === $menu_id ) {
			$this->menu_root = $root_slug;
		}
	}

	public function menu( $menu_id ) {
		if ( 'WPML' !== $menu_id || ! defined( 'ICL_PLUGIN_PATH' ) ) {
			return;
		}
		global $sitepress;
		if ( ! isset( $sitepress ) || ( method_exists( $sitepress, 'get_setting' ) && ! $sitepress->get_setting( 'setup_complete' ) ) ) {
			return;
		}

		global $sitepress_settings;

		if ( ! isset( $sitepress_settings['existing_content_language_verified'] ) || ! $sitepress_settings['existing_content_language_verified'] ) {
			return;
		}

		if ( current_user_can( 'wpml_manage_string_translation' ) ) {
			$menu               = array();
			$menu['order']      = 1300;
			/* translators: Name of the Packages page in the WPML menu, and the heading of that page. Noun, plural. */
			$menu['page_title'] = __( 'Packages', 'wpml-string-translation' );
			/* translators: Name of the Packages page in the WPML menu, and the heading of that page. Noun, plural. */
			$menu['menu_title'] = __( 'Packages', 'wpml-string-translation' );
			$menu['capability'] = 'wpml_manage_string_translation';
			$menu['menu_slug']  = self::MENU_SLUG;
			$menu['function']   = array(
				'WPML_Package_Translation_HTML_Packages',
				'package_translation_menu',
			);

			do_action( 'wpml_admin_menu_register_item', $menu );
			$this->admin_register_scripts();
		}
	}

	function admin_register_scripts() {
		wp_register_script( 'wpml-package-trans-man-script', WPML_PACKAGE_TRANSLATION_URL . '/resources/js/wpml_package_management.js', array( 'jquery' ) );
	}

	function admin_enqueue_scripts( $hook ) {
		if (
			$this->is_packages_management_screen()
			|| $this->is_support_packages_screen()
		) {
			wp_enqueue_script( 'wpml-package-trans-man-script' );
		}
	}

	private function is_packages_management_screen() {
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';

		return self::MENU_SLUG === $page;
	}

	private function is_support_packages_screen() {
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		$tool = isset( $_GET['tool'] ) ? sanitize_text_field( wp_unslash( $_GET['tool'] ) ) : '';

		return self::SUPPORT_PAGE_SLUG === $page && self::SUPPORT_TOOL_PACKAGES === $tool;
	}
}
