<?php

use WPML\ST\Gettext\AutoRegisterSettings;
use WPML\ST\StringsFilter\Translator;
use function WPML\Container\make;
use WPML\ST\Gettext\Filters\StringHighlighting;
use WPML\StringTranslation\Application\Setting\Repository\SettingsRepositoryInterface;
use WPML\StringTranslation\Infrastructure\TranslateEverything\EnglishSourceLanguage;
use WPML\StringTranslation\Infrastructure\WordPress\HookHandler\HasKeyInSettingsFilter;
use WPML\ST\AutoRegisterStringsNotice;
use WPML\ST\AdminTexts\SendStringsForTranslationNotice;

class WPML_String_Translation {

	const CACHE_GROUP = 'wpml-string-translation';

	private $load_priority = 400;

	private $messages = array();

	private $string_filters = array();

	private $active_languages;

	private $current_string_language_cache = array();

	private $string_factory;

	private $admin_language;

	private $is_admin_action_from_referer;

	protected $sitepress;

	private $cache;

	private $filesToScanRepository;

	private $settings;

	public function __construct(
		SitePress $sitepress,
		WPML_ST_String_Factory $string_factory,
		\WPML\ST\TranslationFile\FilesToScanRepository $filesToScanRepository,
		\WPML\ST\Gettext\Settings $settings
	) {
		$this->sitepress             = $sitepress;
		$this->string_factory        = $string_factory;
		$this->filesToScanRepository = $filesToScanRepository;
		$this->settings              = $settings;
	}

	public function set_basic_hooks() {
		if ( $this->sitepress->get_wp_api()->constant( 'WPML_TM_VERSION' ) ) {
			add_action( 'wpml_tm_loaded', array( $this, 'load' ) );
		} else {
			add_action(
				'wpml_loaded',
				array( $this, 'load' ),
				$this->load_priority
			);
		}
		add_action(
			'plugins_loaded',
			array( $this, 'check_db_for_gettext_context' ),
			1000
		);
		add_action(
			'wpml_language_has_switched',
			array( $this, 'wpml_language_has_switched' )
		);

		add_filter( 'screen_settings', [ $this, 'show_screen_options' ], 10, 2 );
	}

	function init_active_languages() {
		$this->active_languages = $this->sitepress->get_supported_language_codes();
	}

	function load() {
		global $sitepress, $wpdb;

		if ( ! $sitepress || ! $sitepress->get_setting( 'setup_complete' ) ) {
			return;
		}

		add_action( 'init', [ $this, 'init' ] );

		$factory = new WPML_ST_Upgrade_Command_Factory( $wpdb, $sitepress );
		$upgrade = new WPML_ST_Upgrade( $sitepress, $factory );
		$upgrade->run();

		\WPML\ST\Upgrade\Deferred\Runner::register( $upgrade );

		$this->init_active_languages();

		$wpml_string_shortcode = new WPML\ST\Shortcode( $wpdb );
		$wpml_string_shortcode->init_hooks();

		wpml_st_load_admin_texts();

		$action_filter_loader = new WPML_Action_Filter_Loader();
		$action_filter_loader->load(
			array(
				'WPML_Slug_Translation_Factory',
			)
		);

		add_filter( 'pre_update_option_blogname', array( $this, 'pre_update_option_blogname' ), 5, 2 );
		add_filter( 'pre_update_option_blogdescription', array( $this, 'pre_update_option_blogdescription' ), 5, 2 );


		add_action( 'icl_ajx_custom_call', array( $this, 'ajax_calls' ), 10, 2 );

		add_filter( 'WPML_ST_strings_language', array( $this, 'get_strings_language' ) );
		add_filter( 'wpml_st_strings_language', array( $this, 'get_strings_language' ) );

		add_action( 'wpml_st_delete_all_string_data', array( $this, 'delete_all_string_data' ), 10, 1 );

		add_filter( 'wpml_st_string_status', array( $this, 'get_string_status_filter' ), 10, 2 );
		add_filter( 'wpml_string_id', array( $this, 'get_string_id_filter' ), 10, 2 );
		add_filter( 'wpml_get_string_language', array( $this, 'get_string_language_filter' ), 10, 3 );

		do_action( 'wpml_st_loaded' );
	}

	public static function user_can_change_string_language() {
		return current_user_can( 'manage_options' ) || current_user_can( 'manage_translations' );
	}

	public function admin_script_change_string_lang() {

		$handle = 'wpml-st-change-lang';

		wp_register_script(
			$handle,
			WPML_ST_URL . '/res/js/change_string_lang.js',
			array( 'jquery', 'jquery-ui-dialog', 'wpml-st-scripts' ),
			WPML_ST_VERSION
		);

		wp_enqueue_script( $handle );

		wp_localize_script(
			$handle,
			'wpml_st_change_lang_data',
			[
				'nonce'           => wp_create_nonce( 'wpml_change_string_language_nonce' ),
				/* translators: Shown when changing the language of the selected strings failed and the server gave no reason of its own. */
				'errorText'       => __( 'The language of these strings could not be changed. Please reload the page and try again.', 'wpml-string-translation' ),
				/* translators: Shown on the String Translation screen when a bulk change (target language or translation priority) is chosen with no strings selected. */
				'noSelectionText' => __( 'Select the strings you want to change first.', 'wpml-string-translation' ),
			]
		);
	}

	public function admin_script_change_string_domain() {

		$handle = 'wpml-st-change-domain';

		wp_register_script(
			$handle,
			WPML_ST_URL . '/res/js/change_string_domain_lang.js',
			array( 'jquery', 'jquery-ui-dialog' ),
			WPML_ST_VERSION
		);

		wp_enqueue_script( $handle );

		wp_localize_script(
			$handle,
			'wpml_st_change_domain_data',
			[
				'nonce'     => wp_create_nonce( 'wpml_change_string_domain_language_nonce' ),
				/* translators: Shown when changing the language of a domain's strings failed and the server gave no reason of its own. */
				'errorText' => __( 'The language of this domain could not be changed. Please reload the page and try again.', 'wpml-string-translation' ),
			]
		);

	}

	public function admin_scripts() {

		$handle = 'wpml-st-scripts';

		wp_register_script(
			$handle,
			WPML_ST_URL . '/res/js/scripts.js',
			array( 'jquery', 'jquery-ui-dialog' ),
			WPML_ST_VERSION
		);

		wp_enqueue_script( $handle );

		wp_localize_script(
			$handle,
			'wpml_scripts_data',
			[
				'nonce_icl_st_pop_download_nonce' => wp_create_nonce( 'icl_st_pop_download_nonce' ),
			]
		);

	}

	private function maybe_add_script_for_string_tracking_modal_iframe() {
		$trackingValueKey                                = 'icl_string_track_value';
		$trackingContextKey                              = 'icl_string_track_context';
		$isRenderingStringTrackingModalContentIntoIframe = (
			isset( $_GET[ $trackingValueKey ], $_GET[ $trackingContextKey ] ) &&
			strlen( $_GET[ $trackingValueKey ] ) > 0 && strlen( $_GET[ $trackingContextKey ] ) > 0
		);

		if ( ! $isRenderingStringTrackingModalContentIntoIframe ) {
			return;
		}

		$cssHandle = 'wpml-st-loaded-page-with-tracked-string';
		wp_register_style( $cssHandle, false );
		wp_enqueue_style( $cssHandle );
		wp_add_inline_style(
			$cssHandle,
			"
            .wpml-st-loaded-page-with-tracked-string-highlight, .wpml-st-loaded-page-with-tracked-string-highlight * {
                color: {$this->settings->getTrackStringColor()} !important;
            }
        "
		);

		$handle = 'wpml-st-loaded-page-with-tracked-string';
		wp_enqueue_script(
			$handle,
			WPML_ST_URL . '/res/js/loadedPageWithTrackedString.js',
			[],
			WPML_ST_VERSION
		);
		wp_localize_script(
			$handle,
			'wpml_st_loaded_page_with_tracked_string_data',
			[
				'tokenStart' => StringHighlighting::HIGHLIGHT_ID_TO_REPLACE_IN_HTML_START,
				'tokenEnd'   => StringHighlighting::HIGHLIGHT_ID_TO_REPLACE_IN_HTML_END,
				'hgColor'    => $this->settings->getTrackStringColor(),
			]
		);
	}

	function init() {

		global $wpdb, $sitepress;

		load_plugin_textdomain( 'wpml-string-translation', false, WPML_ST_FOLDER . '/locale' );
		AutoRegisterStringsNotice::init();
		SendStringsForTranslationNotice::init();

		$this->maybe_add_script_for_string_tracking_modal_iframe();

		if ( is_admin() ) {
			wp_enqueue_style( 'thickbox' );
			wp_enqueue_script( 'jquery' );
			wp_enqueue_script( 'thickbox' );

			$reset = new WPML_ST_Reset( $wpdb );
			add_action( 'wpml_reset_plugins_after', array( $reset, 'reset' ) );
		}

		add_action( 'wpml_admin_menu_configure', array( $this, 'menu' ) );

		add_filter( 'plugin_action_links', array( $this, 'plugin_action_links' ), 10, 2 );

		$current_page = \WPML\SuperGlobals\Request::page();
		if ( $current_page && is_admin() ) {

			$allowed_pages_for_resources = array( WPML_ST_FOLDER . '/menu/string-translation.php' );
			if ( in_array( $current_page, $allowed_pages_for_resources, true ) && self::user_can_change_string_language() && empty( $_POST ) ) {
				add_action( 'admin_enqueue_scripts', array( $this, 'admin_script_change_string_lang' ) );
			}

			$allowed_pages_for_resources[] = ICL_PLUGIN_FOLDER . '/menu/theme-localization.php';
			if ( in_array( $current_page, $allowed_pages_for_resources, true ) ) {
				add_action( 'admin_enqueue_scripts', array( $this, 'admin_script_change_string_domain' ) );
				add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );

				wp_enqueue_script( 'wp-color-picker' );
				wp_enqueue_style( 'wp-color-picker' );
				wp_enqueue_script( 'wpml-st-settings', WPML_ST_URL . '/res/js/settings.js', array( 'jquery' ), WPML_ST_VERSION );
				wp_enqueue_script( OTGS_Assets_Handles::POPOVER_TOOLTIP );
				wp_enqueue_style( OTGS_Assets_Handles::POPOVER_TOOLTIP );
				wp_enqueue_script( 'wpml-st-modal-form', WPML_ST_URL . '/res/js/st_modal_form.js', array( 'jquery', 'jquery-ui-dialog' ), WPML_ST_VERSION );
				wp_enqueue_script( 'wpml-translate-user-fields', WPML_ST_URL . '/res/js/translate-user-fields.js', array( 'jquery' ), WPML_ST_VERSION );
				wp_enqueue_script( 'wpml-auto-register-strings', WPML_ST_URL . '/res/js/auto-register-strings.js', array( 'jquery', 'wpml-st-scripts' ), WPML_ST_VERSION );
				wp_enqueue_script( 'wpml-plugin-list-table-filter', WPML_ST_URL . '/res/js/wpml-plugin-list-table-filter.js', array( 'jquery' ), WPML_ST_VERSION );
				wp_enqueue_style( 'wpml-st-styles', WPML_ST_URL . '/res/css/style.css', array(), WPML_ST_VERSION );
				wp_enqueue_style( 'wpml-dialog', ICL_PLUGIN_URL . '/res/css/dialog.css', array( 'otgs-dialogs' ), ICL_SITEPRESS_VERSION );
				wp_enqueue_style( 'wp-jquery-ui-dialog' );
				add_action( 'admin_enqueue_scripts', array( $this, 'admin_script_exec_batch_action' ) );
			}
		}

		add_action( 'wpml_custom_localization_type', array( $this, 'localization_type_ui' ) );
		\WPML\Request\Adapter\Ajax::register( 'icl_st_pop_download', \WPML\Request\Policy\Policy::capability( [ 'wpml_manage_string_translation', 'manage_translations' ], \WPML\Request\Policy\Authenticity::actionNonce( 'icl_st_pop_download_nonce', 'wpnonce' ) ), array( $this, 'plugin_po_file_download' ) );
		\WPML\Request\Adapter\Ajax::register( 'wpml_change_string_lang', \WPML\Request\Policy\Policy::capability( [ 'manage_options', 'manage_translations' ], \WPML\Request\Policy\Authenticity::actionNonce( 'wpml_change_string_language_nonce', 'wpnonce' ) ), array( $this, 'change_string_lang_ajax_callback' ) );
		\WPML\Request\Adapter\Ajax::register( 'wpml_change_string_lang_of_domain', \WPML\Request\Policy\Policy::capability( [ 'manage_options', 'manage_translations' ], \WPML\Request\Policy\Authenticity::actionNonce( 'wpml_change_string_domain_language_nonce', 'wpnonce' ) ), array( $this, 'change_string_lang_of_domain_ajax_callback' ) );
		\WPML\Request\Adapter\Ajax::register( 'load_localization_type_ui_html', \WPML\Request\Policy\Policy::capability( 'manage_options', \WPML\Request\Policy\Authenticity::none( 'read-only render of the localization-type settings UI; no state change' ) ), array( $this, 'localization_type_ui_html_ajax' ) );

		$auto_register_settings = WPML\Container\make( AutoRegisterSettings::class );
		\WPML\Request\Adapter\Ajax::register( 'wpml_st_exclude_contexts', \WPML\Request\Policy\Policy::capability( 'manage_options', \WPML\Request\Policy\Authenticity::actionNonce( 'wpml-st-cancel-button', 'nonce' ) ), array( $auto_register_settings, 'saveExcludedContexts' ) );

		return true;
	}

	public function admin_script_exec_batch_action() {
		$handle = 'wpml_st_exec_batch_action';

		wp_register_script(
			$handle,
			WPML_ST_URL . '/res/js/exec-batch-action.js',
			array( 'jquery' ),
			WPML_ST_VERSION
		);

		wp_enqueue_script( $handle );

		wp_localize_script(
			$handle,
			'wpml_st_exec_batch_action_data',
			[
				/* translators: Alert on the String Translation screen when a bulk action on a whole domain (delete, change language, change priority) could not finish. */
				'stoppedText'                     => __( 'The action stopped before it finished. Try again to finish it.', 'wpml-string-translation' ),
				/* translators: Alert on the String Translation screen when the first request of a bulk action on a whole domain (the count of the strings to change) got no answer from the server, so nothing was changed. */
				'notStartedText'                  => __( 'The action did not start because the server did not answer. Try again.', 'wpml-string-translation' ),
				'countStringsInDomain'            => [
					'endpoint' => \WPML\ST\BatchAction\CountStringsInDomain::class,
					'nonce'    => \WPML\LIB\WP\Nonce::create( \WPML\ST\BatchAction\CountStringsInDomain::class ),
				],
				'deleteStringsInDomain'           => [
					'endpoint' => \WPML\ST\BatchAction\DeleteStringsInDomain::class,
					'nonce'    => \WPML\LIB\WP\Nonce::create( \WPML\ST\BatchAction\DeleteStringsInDomain::class ),
				],
				'initChangeStringLangOfDomain'    => [
					'endpoint' => \WPML\ST\BatchAction\InitChangeStringLangOfDomain::class,
					'nonce'    => \WPML\LIB\WP\Nonce::create( \WPML\ST\BatchAction\InitChangeStringLangOfDomain::class ),
				],
				'changeLanguageOfStringsInDomain' => [
					'endpoint' => \WPML\ST\BatchAction\ChangeLanguageOfStringsInDomain::class,
					'nonce'    => \WPML\LIB\WP\Nonce::create( \WPML\ST\BatchAction\ChangeLanguageOfStringsInDomain::class ),
				],
				'countStringsInDomainWithDifferentPriority' => [
					'endpoint' => \WPML\ST\BatchAction\CountStringsInDomainWithDifferentPriority::class,
					'nonce'    => \WPML\LIB\WP\Nonce::create( \WPML\ST\BatchAction\CountStringsInDomainWithDifferentPriority::class ),
				],
				'changeTranslationPriorityBatchOfStringsInDomain' => [
					'endpoint' => \WPML\ST\BatchAction\ChangeTranslationPriorityOfStringsInDomain::class,
					'nonce'    => \WPML\LIB\WP\Nonce::create( \WPML\ST\BatchAction\ChangeTranslationPriorityOfStringsInDomain::class ),
				],
			]
		);
	}

	function translate_string( $context, $name, $original_value = false, &$has_translation = null, $target_lang = null ) {

		return icl_translate( $context, $name, $original_value, false, $has_translation, $target_lang );
	}

	function add_message( $text, $type = 'updated' ) {
		$this->messages[] = array(
			'type' => $type,
			'text' => $text,
		);
	}

	function show_messages() {
		if ( ! empty( $this->messages ) ) {
			foreach ( $this->messages as $m ) {
				printf( '<div class="%s fade"><p>%s</p></div>', $m['type'], $m['text'] );
			}
		}
	}

	function ajax_calls( $call, $data ) {
		require_once WPML_ST_PATH . '/inc/admin-texts/wpml-admin-text-configuration.php';

		switch ( $call ) {

			case 'icl_st_delete_strings':
				$arr = explode( ',', $data['value'] );
				wpml_unregister_string_multi( $arr );
				echo '1';
				break;
		}
	}

	function menu( $menu_id ) {
		if ( 'WPML' !== $menu_id ) {
			return;
		}

		if ( ! $this->sitepress || ! $this->sitepress->get_wp_api()->constant( 'ICL_PLUGIN_PATH' ) ) {
			return;
		}

		$setup_complete = apply_filters( 'wpml_get_setting', false, 'setup_complete' );
		if ( ! $setup_complete ) {
			return;
		}

		global $wpdb;
		$existing_content_language_verified = apply_filters(
			'wpml_get_setting',
			false,
			'existing_content_language_verified'
		);

		if ( ! $existing_content_language_verified ) {
			return;
		}

		if ( current_user_can( 'wpml_manage_string_translation' ) || current_user_can( 'manage_translations' ) ) {
			$menu               = array();
			$menu['order']      = 800;
			/* translators: Name of the String Translation page in the WPML menu, the heading of that page, and the title of its section on the WPML settings page. */
			$menu['page_title'] = __( 'String Translation', 'wpml-string-translation' );
			/* translators: Name of the String Translation page in the WPML menu, the heading of that page, and the title of its section on the WPML settings page. */
			$menu['menu_title'] = __( 'String Translation', 'wpml-string-translation' );
			$menu['capability'] = current_user_can( 'wpml_manage_string_translation' ) ? 'wpml_manage_string_translation' : 'manage_translations';
			$menu['menu_slug']  = WPML_ST_FOLDER . '/menu/string-translation.php';

			do_action( 'wpml_admin_menu_register_item', $menu );
		}
	}

	public function show_screen_options( $status, $args ) {
		if ( 'wpml-string-translation/menu/string-translation' !== $args->base ) {
			return $status;
		}

		$page_builders = (array) apply_filters( 'wpml_get_page_builder_text_domains', [] );

		if ( empty( $page_builders ) ) {
			return $status;
		}

		if ( isset( $_POST['screen-options-apply'] ) ) {
			check_admin_referer( 'screen-options-nonce', 'screenoptionnonce' );
			$user_meta = isset( $_POST['context_page_builder_hide_options'] ) ? $_POST['context_page_builder_hide_options'] : [];
			update_user_meta( get_current_user_id(), 'context_page_builder_hide_options', $user_meta );
		} else {
			$user_meta = get_user_meta( get_current_user_id(), 'context_page_builder_hide_options', true );
		}

		$user_meta = is_array( $user_meta ) ? $user_meta : [];

		$screen_options = [];
		foreach ( $page_builders as $option_name ) {
			$screen_options[] = [
				'option'  => $option_name,
				'title'   => ucwords( (string) $option_name ),
				'checked' => array_key_exists( $option_name, $user_meta ),
			];
		}

		ob_start();
		?>

		<fieldset class="metabox-prefs">
			<legend><?php esc_html_e( 'Show page builder packages', 'wpml-string-translation' ); ?></legend>

			<?php foreach ( $screen_options as $screen_option ) : ?>
				<label>
					<input class="hide-column-tog"
						   name="context_page_builder_hide_options[<?php echo esc_textarea( $screen_option['option'] ); ?>]"
						   type="checkbox"
						   id="<?php echo esc_textarea( $screen_option['option'] ); ?>" <?php checked( $screen_option['checked'] ); ?>/> <?php echo esc_html( $screen_option['title'] ); ?>
				</label>
			<?php endforeach; ?>

		</fieldset>
		<?php submit_button( /* translators: Button label that saves the settings on the String Translation page and in its dialogs. Verb, imperative. */ __( 'Apply', 'wpml-string-translation' ), 'primary', 'screen-options-apply', true ); ?>

		<?php
		return ob_get_clean();
	}

	function plugin_action_links( $links, $file ) {
		 $this_plugin = basename( WPML_ST_PATH ) . '/plugin.php';
		if ( $file == $this_plugin ) {
			$links[] = '<a href="admin.php?page=tm/menu/main.php&tab=strings">' .
				/* translators: Link next to WPML String Translation on the WordPress plugins screen: it opens the settings page. Verb, imperative. */
				__( 'Configure', 'wpml-string-translation' ) . '</a>';
		}

		return $links;
	}

	function localization_type_ui_html_ajax() {
		if ( ! current_user_can( 'manage_options' ) ) {
			exit( 0 );
		}

		echo $this->localization_type_ui();
		exit( 0 );
	}

	public function localization_type_ui() {
		$plugin_localization_factory = new WPML_ST_Plugin_Localization_UI_Factory();
		$plugin_localization         = $plugin_localization_factory->create();

		$theme_localization_factory = new WPML_ST_Theme_Localization_UI_Factory();
		$theme_localization         = $theme_localization_factory->create();

		$other_localization_factory = new \WPML\ST\ThemePluginLocalization\OtherLocalizationUIFactory();
		$other_localization         = $other_localization_factory->create();

		$localization = new WPML_Theme_Plugin_Localization_UI();

		echo '<details class="wpml-section wpml-st-localization wpml-st-localization-details" id="wpml-st-localization">
				<summary class="wpml-st-localization-summary">
					<div class="wpml-st-localization-summary-text">
						<h3 class="wpml-st-localization-title">' . esc_html__( 'Not seeing an admin text you need?', 'wpml-string-translation' ) . '</h3>
						<p class="wpml-st-localization-description">' . esc_html__( 'Scan themes, plugins, and WordPress to register more admin texts.', 'wpml-string-translation' ) . '</p>
					</div>
					<svg class="wpml-st-localization-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
						<path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"></path>
					</svg>
				</summary>
				<div id="wpml-st-localization-section" class="wpml-section-content wpml-section-content-wide">';
		$this->renderChangedMoFilesBlock( $plugin_localization );

		do_action( 'wpml_st_before_localization_ui_table' );

		echo '		<table id="wpml-st-localization-table" class="widefat striped">';
		echo $localization->renderTemplate(
			'theme-plugin-localization-ui-table-header.twig',
			[
				/* translators: Column heading in the tables of texts, of packages and of themes and plugins: the name of the item in the row. Noun, not the verb "to name". */
				'name'               => __( 'Name', 'wpml-string-translation' ),
				/* translators: Column heading on the Theme and plugins localization page: how far the texts of that theme or plugin are translated. Noun. */
				'status'             => __( 'Status', 'wpml-string-translation' ),
				/* translators: Column heading on the Theme and plugins localization page: the text domain, the short name a theme or plugin uses for its texts. */
				'textdomain'         => __( 'Textdomain', 'wpml-string-translation' ),
				/* translators: Column heading on the Theme and plugins localization page: the buttons for that theme or plugin. Noun. */
				'action'             => __( 'Action', 'wpml-string-translation' ),
				'completed_title'    => __( 'Completely translated strings', 'wpml-string-translation' ),
				'needs_update_title' => __( 'Strings that need translation', 'wpml-string-translation' ),
				'nonces'             => [
					'scan_folder' => [
						'action' => WPML_ST_Theme_Plugin_Scan_Dir_Ajax_Factory::AJAX_ACTION,
						'nonce'  => wp_create_nonce( WPML_ST_Theme_Plugin_Scan_Dir_Ajax_Factory::AJAX_ACTION ),
					],
					'scan_files'  => [
						'action' => WPML_ST_Theme_Plugin_Scan_Files_Ajax_Factory::AJAX_ACTION,
						'nonce'  => wp_create_nonce( WPML_ST_Theme_Plugin_Scan_Files_Ajax_Factory::AJAX_ACTION ),
					],
				],
				'endpoints'          => [
					'update_stats' => [
						'endpoint' => \WPML\ST\StringsScanning\UpdateStats::class,
						'nonce'    => \WPML\LIB\WP\Nonce::create( \WPML\ST\StringsScanning\UpdateStats::class ),
					],
				],
			]
		);
		echo $localization->render( $theme_localization );
		echo $localization->render( $plugin_localization );
		echo $localization->render( $other_localization );
		echo '		</table>
					<button id="wpml_theme_plugin_localization_scan" type="button" class="button-primary wpml-button base-btn btn-scan section-submit-button" disabled="disabled">' . __( 'Scan selected components for strings', 'wpml-string-translation' ) . '</button>
				</div>
			</details>';
	}

	private function renderPluginsWithMissingTranslationsBlock( $pluginLocalization ) {
		$pluginNames = $pluginLocalization->getActivePluginNamesWithoutRegisteredTranslationFiles();

		if ( empty( $pluginNames ) || $this->shouldSkipMissingTranslationsBlockRendering() ) {
			return '';
		}

		return $this->buildMissingTranslationsMessage( $pluginNames );
	}

	private function shouldSkipMissingTranslationsBlockRendering(): bool {
		return apply_filters(
			HasKeyInSettingsFilter::FILTER_NAME,
			SettingsRepositoryInterface::WAS_FRONTEND_VISITED_KEY
		);
	}

	private function buildMissingTranslationsMessage( array $pluginNames ): string {
		$wrap = function( $name ) {
			return '<b>' . $name . '</b>';
		};

		$parts             = $this->splitLastElement( $pluginNames );
		$formattedNames    = implode( ', ', array_map( $wrap, $parts['rest'] ) );
		$formattedLastName = $parts['last'] ? $wrap( $parts['last'] ) : null;

		$message = $this->getTranslatedMissingTranslationsMessage( count( $pluginNames ), $formattedNames, $formattedLastName );

		return $this->wrapMissingTranslationsMessageInHtml( $message );
	}

	private function getTranslatedMissingTranslationsMessage( int $count, string $namesList, ?string $lastItem = null ): string {
		if ( 1 === $count ) {
			$names = (string) $lastItem;
		} else {
			/* translators: Joins the last two names in a list of plugins, as in "Contact Form 7 and Yoast SEO". %1$s: the names before the last one, already joined by commas, %2$s: the last name. */
			$names = sprintf( __( '%1$s and %2$s', 'wpml-string-translation' ), $namesList, $lastItem );
		}

		return sprintf(
			/* translators: Notice shown when WPML cannot find the translation files of some plugins. %s: the name of the plugin, or the names of all of them, joined into a list. */
			__( 'WPML could not detect the translation files (.mo) for %s. To fix this, visit your site\'s frontend in a secondary language.', 'wpml-string-translation' ),
			$names
		);
	}

	private function splitLastElement( array $items ): array {
		if ( empty( $items ) ) {
			return [
				'rest' => [],
				'last' => null,
			];
		}

		if ( count( $items ) === 1 ) {
			return [
				'rest' => [],
				'last' => $items[0],
			];
		}

		$last = array_pop( $items );

		return [
			'rest' => $items,
			'last' => $last,
		];
	}

	private function wrapMissingTranslationsMessageInHtml( string $message ): string {
		/* translators: Tooltip on the link in the notice that lists plugins whose translation files are missing. "This" is following that link and opening the site in another language. */
		$title = __( 'This will load the translation files and WPML will be able to scan them.', 'wpml-string-translation' );

		return sprintf(
			'<div class="info-wrap">
				<div class="info-content-wrap info-content-wrap--light">
					<div class="info-content">
						<p>%s<span class="help-icon js-otgs-popover-tooltip" title="%s" /></p>
					</div>
				</div>
			</div>',
			$message,
			$title
		);
	}

	private function renderChangedMoFilesBlock( $pluginLocalization ) {
		if ( ! $this->filesToScanRepository->hasFilesToScan() ) {
			return;
		}

		echo '<div class="info-wrap" id="wpml-st-changed-mo-files-form">
				<p>' . __( 'Scan the selected components to make new texts available for translation.', 'wpml-string-translation' ) . '</p>
				' . $this->renderPluginsWithMissingTranslationsBlock( $pluginLocalization ) . '
				<div class="info-content-wrap">
					<div class="info-content">
						<p class="heading">' . __( 'Updated or new translation files detected', 'wpml-string-translation' ) . '</p>
						<p>' . __( 'WPML has found new or updated translation (.mo) files and needs to scan them to find translatable strings.', 'wpml-string-translation' ) . '</p>
						<form><button id="select-all-changed-mo-checkboxes-to-rescan" type="button" class="button-primary wpml-button base-btn btn-select-all">' . __( 'Select affected components', 'wpml-string-translation' ) . '</button></form>
					</div>
				</div>
			</div>';
	}

	function scan_theme_for_strings() {
		require_once WPML_ST_PATH . '/inc/gettext/wpml-theme-string-scanner.class.php';

		$scan_for_strings = new WPML_Theme_String_Scanner( wpml_get_filesystem() );
		$scan_for_strings->scan();
	}

	function scan_plugins_for_strings() {
		require_once WPML_ST_PATH . '/inc/gettext/wpml-plugin-string-scanner.class.php';
		$scan_for_strings = new WPML_Plugin_String_Scanner( wpml_get_filesystem() );
		$scan_for_strings->scan();
	}

	function plugin_po_file_download( $file = false, $recursion = 0 ) {

		if ( ! isset( $_GET['wpnonce'] ) || ! wp_verify_nonce( $_GET['wpnonce'], 'icl_st_pop_download_nonce' ) ) {
			die( 'verification failed' );
		}

		if ( ! current_user_can( 'wpml_manage_string_translation' ) && ! current_user_can( 'manage_translations' ) ) {
			die( 'permission denied' );
		}

		global $__wpml_st_po_file_content;

		if ( empty( $file ) && ! empty( $_GET['file'] ) ) {
			$file = WPML_PLUGINS_DIR . '/' . \WPML\API\Sanitize::string( $_GET['file'] );
		}

		if ( empty( $file ) || false === WPML_ST_Path_Confinement::resolve_contained( $file, WPML_PLUGINS_DIR ) ) {
			return;
		}

		if ( is_null( $__wpml_st_po_file_content ) ) {
			$__wpml_st_po_file_content = '';
		}

		require_once WPML_ST_PATH . '/inc/potx.php';
		require_once WPML_ST_PATH . '/inc/potx-callback.php';

		if ( is_file( $file ) && WPML_PLUGINS_DIR == dirname( $file ) ) {

			_potx_process_file( $file, 0, 'wpml_st_pos_scan_store_results', '_potx_save_version', '' );
		} else {

			if ( ! $recursion ) {
				$file = dirname( $file );
			}

			if ( is_dir( $file ) ) {
				$dh = opendir( $file );
				while ( $dh && false !== ( $f = readdir( $dh ) ) ) {
					if ( 0 === strpos( $f, '.' ) ) {
						continue;
					}
					$this->plugin_po_file_download( $file . '/' . $f, $recursion + 1 );
				}
			} elseif ( preg_match( '#(\.php|\.inc)$#i', $file ) ) {
				_potx_process_file( $file, 0, 'wpml_st_pos_scan_store_results', '_potx_save_version', '' );
			}
		}

		if ( ! $recursion ) {
			$po  = WPML_PO_Parser::get_po_file_header();
			$po .= $__wpml_st_po_file_content;

			$filename = isset( $_GET['domain'] ) ?
				(string) \WPML\API\Sanitize::string( $_GET['domain'] ) :
				basename( $file );

			header( 'Content-Type: application/force-download' );
			header( 'Content-Type: application/octet-stream' );
			header( 'Content-Type: application/download' );
			header( 'Content-Transfer-Encoding: binary' );
			header( 'Content-Disposition: attachment; filename="' . $filename . '.po"' );
			header( 'Content-Length: ' . strlen( $po ) );
			echo $po;
			exit( 0 );
		}
	}

	public function estimate_word_count( $string, $lang_code ) {
		$string = strip_tags( $string );

		return in_array(
			$lang_code,
			array(
				'ja',
				'ko',
				'zh-hans',
				'zh-hant',
				'mn',
				'ne',
				'hi',
				'pa',
				'ta',
				'th',
			)
		) ? strlen( $string ) / 6
			: count( explode( ' ', $string ) );
	}

	function pre_update_option_blogname( $value, $old_value ) {
		return $this->pre_update_option_settings(
			WPML_ST_Blog_Name_And_Description_Hooks::STRING_NAME_BLOGNAME,
			$value,
			$old_value
		);
	}

	function pre_update_option_blogdescription( $value, $old_value ) {
		return $this->pre_update_option_settings(
			WPML_ST_Blog_Name_And_Description_Hooks::STRING_NAME_BLOGDESCRIPTION,
			$value,
			$old_value
		);
	}

	function pre_update_option_settings( $option, $value, $old_value ) {
		$wp_api = $this->sitepress->get_wp_api();
		if ( $wp_api->is_multisite()
			 && $wp_api->ms_is_switched()
			 && ! $this->sitepress->get_setting( 'setup_complete' )
		) {
			return $value;
		}

		$option = new WPML_ST_Admin_Blog_Option(
			$this->sitepress,
			$this,
			$option
		);

		return $option->pre_update_filter( $old_value, $value );
	}

	public function get_admin_option( $option_name, $language_code = '' ) {

		return new WPML_ST_Admin_Option_Translation(
			$this->sitepress,
			$this,
			$option_name,
			$language_code
		);
	}

	public function string_factory() {

		return $this->string_factory;
	}

	public function clear_string_filter( $lang_code ) {
		unset( $this->string_filters[ $lang_code ] );
	}

	public function get_string_filter( $lang ) {

		if ( true === (bool) $this->active_languages && in_array( $lang, $this->active_languages, true ) ) {
			return $this->get_admin_string_filter( $lang );
		} else {
			return null;
		}
	}

	public function get_admin_string_filter( $lang ) {
		global $sitepress_settings, $wpdb, $sitepress;

		if ( isset( $sitepress_settings['st']['db_ok_for_gettext_context'] ) ) {
			if ( ! ( isset( $this->string_filters[ $lang ] )
					 && 'WPML_Register_String_Filter' == get_class( $this->string_filters[ $lang ] ) )
			) {
				$this->string_filters[ $lang ] = isset( $this->string_filters[ $lang ] ) ? $this->string_filters[ $lang ] : false;

				$auto_register_settings = WPML\Container\make( AutoRegisterSettings::class );

				$this->string_filters[ $lang ] = new WPML_Register_String_Filter(
					$wpdb,
					$sitepress,
					$this->string_factory,
					make( Translator::class, [ ':language' => $lang ] ),
					$auto_register_settings->getExcludedDomains()
				);
			}

			return $this->string_filters[ $lang ];
		} else {
			return null;
		}
	}

	public function get_strings_language( $language = '' ) {
		$string_settings = $this->get_strings_settings();

		$string_language = $language ? $language : EnglishSourceLanguage::resolveForSite();
		if ( isset( $string_settings['strings_language'] ) ) {
			$string_language = $string_settings['strings_language'];
		}

		return $string_language;
	}

	public function delete_all_string_data( $string_id ) {
		global $wpdb;

		$wpdb->query(
			$wpdb->prepare( "DELETE FROM {$wpdb->prefix}icl_string_positions WHERE string_id = %d", $string_id )
		);
		$wpdb->query(
			$wpdb->prepare( "DELETE FROM {$wpdb->prefix}icl_string_translations WHERE string_id = %d", $string_id )
		);
		$wpdb->query(
			$wpdb->prepare( "DELETE FROM {$wpdb->prefix}icl_strings WHERE id = %d", $string_id )
		);

		do_action( 'wpml_st_string_unregistered' );
	}

	public function get_strings_settings() {
		global $sitepress;

		if ( version_compare( ICL_SITEPRESS_VERSION, '3.2', '<' ) ) {
			global $sitepress_settings;

			$string_settings = isset( $sitepress_settings['st'] ) ? $sitepress_settings['st'] : array();
		} else {
			$string_settings = $sitepress ? $sitepress->get_string_translation_settings() : array();
		}

		$string_settings['strings_language'] = EnglishSourceLanguage::resolveForSite();
		if ( ! isset( $string_settings['icl_st_auto_reg'] ) ) {
			$string_settings['icl_st_auto_reg'] = 'disable';
		}
		$string_settings['strings_per_page'] = ICL_STRING_TRANSLATION_AUTO_REGISTER_THRESHOLD;

		return $string_settings;
	}

	public function get_string_status_filter( $empty = null, $string_id = 0 ) {
		return $this->get_string_status( $string_id );
	}

	public function get_string_id_filter( $default = null, $string_data = array() ) {
		$result = $default;

		$string_id = $this->get_string_id( $string_data );

		return $string_id ? $string_id : $result;
	}

	private function get_string_status( $string_id ) {
		global $wpdb;
		$status = $wpdb->get_var(
			$wpdb->prepare(
				"
		            SELECT	MIN(status)
		            FROM {$wpdb->prefix}icl_string_translations
		            WHERE
		                string_id=%d
		            ",
				$string_id
			)
		);

		return $status !== null ? (int) $status : null;
	}

	private function get_string_id( $string_data ) {
		$context = isset( $string_data['context'] ) ? $string_data['context'] : null;
		$name    = isset( $string_data['name'] ) ? $string_data['name'] : null;

		$result = null;
		if ( $name && $context ) {
			$preloaded = \WPML\ST\PackageTranslation\StringRowsCache::findIdByName( $context, $name );
			if ( null !== $preloaded ) {
				return (int) $preloaded;
			}

			global $wpdb;
			$string_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$wpdb->prefix}icl_strings WHERE context = %s AND name = %s",
					$context,
					$name
				)
			);

			$result = (int) $string_id;
		}

		return $result;
	}

	public function get_string_language_filter( $empty = null, $domain = '', $name = '' ) {
		global $wpdb;

		$key                         = md5( $domain . '_' . $name );
		list( $string_lang, $found ) = $this->get_cache()->get_with_found( $key );

		if ( ! $found ) {
			$string_lang = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT language FROM {$wpdb->prefix}icl_strings WHERE context = %s AND name = %s",
					$domain,
					$name
				)
			);

			$this->get_cache()->set( $key, $string_lang, 600 );
		}

		return $string_lang;
	}

	public function set_cache( WPML_WP_Cache $cache ) {
		$this->cache = $cache;
	}

	public function get_cache() {
		if ( null === $this->cache ) {
			$this->cache = new WPML_WP_Cache( self::CACHE_GROUP );
		}

		return $this->cache;
	}

	function check_db_for_gettext_context() {
		$string_settings = apply_filters( 'wpml_get_setting', [], 'st' );
		if ( ! isset( $string_settings['db_ok_for_gettext_context'] ) ) {

			if ( function_exists( 'icl_table_column_exists' ) && icl_table_column_exists( 'icl_strings', 'domain_name_context_md5' ) ) {
				$string_settings['db_ok_for_gettext_context'] = true;
				do_action( 'wpml_set_setting', 'st', $string_settings, true );
			}
		}
	}

	public function initialize_wp_and_widget_strings() {
		$this->check_db_for_gettext_context();

		icl_register_string(
			WPML_ST_Blog_Name_And_Description_Hooks::STRING_DOMAIN,
			WPML_ST_Blog_Name_And_Description_Hooks::STRING_NAME_BLOGNAME,
			get_option( 'blogname' )
		);

		icl_register_string(
			WPML_ST_Blog_Name_And_Description_Hooks::STRING_DOMAIN,
			WPML_ST_Blog_Name_And_Description_Hooks::STRING_NAME_BLOGDESCRIPTION,
			get_option( 'blogdescription' )
		);

		wpml_st_init_register_widget_titles();

		$active_text_widgets = array();
		$widgets             = (array) get_option( 'sidebars_widgets' );
		foreach ( $widgets as $k => $w ) {
			if ( 'wp_inactive_widgets' != $k && $k != 'array_version' ) {
				if ( is_array( $widgets[ $k ] ) ) {
					foreach ( $widgets[ $k ] as $v ) {
						if ( preg_match( '#text-([0-9]+)#i', $v, $matches ) ) {
							$active_text_widgets[] = $matches[1];
						}
					}
				}
			}
		}

		$widget_text = get_option( 'widget_text' );
		if ( is_array( $widget_text ) ) {
			foreach ( $widget_text as $k => $w ) {
				if ( ! empty( $w ) && isset( $w['title'], $w['text'] ) && in_array( $k, $active_text_widgets ) && $w['text'] ) {
					icl_register_string( WPML_ST_WIDGET_STRING_DOMAIN, 'widget body - ' . md5( $w['text'] ), $w['text'] );
				}
			}
		}
	}

	public function get_current_string_language( $name ) {
		if ( isset( $this->current_string_language_cache[ $name ] ) ) {
			return $this->current_string_language_cache[ $name ];
		}

		$key              = 'current_language';
		$found            = false;
		$current_language = WPML_Non_Persistent_Cache::get( $key, 'WPML_String_Translation', $found );
		if ( ! $found ) {
			$wp_api           = $this->sitepress->get_wp_api();
			$current_language = $wp_api->constant( 'DOING_AJAX' )
								&& $this->is_admin_action_from_referer()
				? $this->sitepress->user_lang_by_authcookie()
				: $this->sitepress->get_current_language();
			WPML_Non_Persistent_Cache::set( $key, $current_language, 'WPML_String_Translation' );
		}

		if ( $this->should_use_admin_language()
			 && ! WPML_ST_Blog_Name_And_Description_Hooks::is_string( (string) $name ) ) {
			$admin_display_lang = $this->get_admin_language();
			$current_language   = $admin_display_lang ? $admin_display_lang : $current_language;
		}

		$ret = apply_filters(
			'icl_current_string_language',
			$current_language,
			$name
		);
		$this->current_string_language_cache[ $name ] = $ret === 'all'
			? $this->sitepress->get_default_language() : $ret;

		return $this->current_string_language_cache[ $name ];
	}

	public function should_use_admin_language() {
		$key                       = 'should_use_admin_language';
		$found                     = false;
		$should_use_admin_language = WPML_Non_Persistent_Cache::get( $key, 'WPML_String_Translation', $found );
		if ( ! $found ) {
			$wp_api                    = $this->sitepress->get_wp_api();
			$should_use_admin_language = ( $wp_api->constant( 'WP_ADMIN' )
										   && ( $this->is_admin_action_from_referer() || ! $wp_api->constant( 'DOING_AJAX' ) ) )
										 || $this->sitepress->is_admin_originated_rest_request();
			WPML_Non_Persistent_Cache::set( $key, $should_use_admin_language, 'WPML_String_Translation' );
		}

		return $should_use_admin_language;
	}

	public function get_admin_language() {
		if ( $this->sitepress->is_wpml_switch_language_triggered() ) {
			return $this->sitepress->get_admin_language();
		}

		if ( ! $this->admin_language ) {
			$this->admin_language = $this->sitepress->get_admin_language();
		}

		return $this->admin_language;
	}

	private function is_admin_action_from_referer() {
		if ( $this->is_admin_action_from_referer === null ) {
			$this->is_admin_action_from_referer = $this->sitepress->check_if_admin_action_from_referer();
		}

		return $this->is_admin_action_from_referer;
	}

	public function wpml_language_has_switched() {
		$this->current_string_language_cache = array();
	}

	public function change_string_lang_ajax_callback() {
		if ( ! $this->verify_ajax_call( 'wpml_change_string_language_nonce' ) ) {
			die( 'verification failed' );
		}
		check_ajax_referer( 'wpml_change_string_language_nonce', 'wpnonce' );

		if ( ! self::user_can_change_string_language() ) {
			wp_send_json_error( 'not allowed', 403 );

			return;
		}

		$strings = \WPML\Request\Payload::listField(
			$_POST,
			'strings',
			/* translators: Shown on the String Translation screen when a bulk change (target language or translation priority) is chosen with no strings selected. */
			__( 'Select the strings you want to change first.', 'wpml-string-translation' )
		);
		if ( \WPML\Request\Payload::isRefusal( $strings ) ) {
			\WPML\Request\Payload::refuse( $strings );

			return;
		}

		global $wpdb;
		$change_string_language_dialog = new WPML_Change_String_Language_Select( $wpdb, $this->sitepress );

		$string_ids = array_map( 'intval', $strings );
		$lang = \WPML\Language\RequestedLanguage::validate(
			isset( $_POST['language'] ) ? sanitize_text_field( wp_unslash( $_POST['language'] ) ) : '',
			\WPML\Language\RequestedLanguage::SCOPE_CONFIGURED
		);
		if ( null === $lang ) {
			wp_send_json_error( 'invalid language', 400 );

			return;
		}
		$response = $change_string_language_dialog->change_language_of_strings( $string_ids, $lang );

		wp_send_json( $response );
	}

	public function change_string_lang_of_domain_ajax_callback() {
		if ( ! $this->verify_ajax_call( 'wpml_change_string_domain_language_nonce' ) ) {
			die( 'verification failed' );
		}
		check_ajax_referer( 'wpml_change_string_domain_language_nonce', 'wpnonce' );

		if ( ! self::user_can_change_string_language() ) {
			wp_send_json_error( 'not allowed', 403 );

			return;
		}

		global $wpdb, $sitepress;

		$to_lang = \WPML\Language\RequestedLanguage::validate(
			isset( $_POST['language'] ) ? sanitize_text_field( wp_unslash( $_POST['language'] ) ) : '',
			\WPML\Language\RequestedLanguage::SCOPE_CONFIGURED
		);
		if ( null === $to_lang ) {
			wp_send_json_error( 'invalid language', 400 );

			return;
		}
		$from_langs = array_values(
			array_filter(
				array_map(
					function ( $code ) {
						return \WPML\Language\RequestedLanguage::validate( $code, \WPML\Language\RequestedLanguage::SCOPE_CONFIGURED );
					},
					isset( $_POST['langs'] ) ? array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['langs'] ) ) : array()
				)
			)
		);

		if ( ! empty( $_POST['langs'] ) && empty( $from_langs ) ) {
			wp_send_json_error( 'invalid language', 400 );

			return;
		}

		$change_string_language_domain_dialog = make( \WPML_Change_String_Domain_Language_Dialog::class );
		$response                             = $change_string_language_domain_dialog->change_language_of_strings(
			$_POST['domain'],
			$from_langs,
			$to_lang,
			$_POST['use_default'] == 'true'
		);

		wp_send_json( $response );
	}

	private function verify_ajax_call( $ajax_action ) {
		return isset( $_POST['wpnonce'] ) && wp_verify_nonce( $_POST['wpnonce'], $ajax_action );
	}

}
