<?php

namespace WPML\ST\StringsScanning\JS;

use WPML\ST\Rest\Base;
use WPML\ST\WP\App\Resources;
use WPML\StringTranslation\UserInterface\RestApi\StringSettingsApiController;
use WPML\UIPage;

class SettingsHooks implements \IWPML_Action {

	const SECTION_ID = 'ml-content-setup-string-translation';

	const SETTINGS_SECTION = 'string-translation';

	const ANCHOR_ID = 'detect-js-strings';

	const PRIORITY_AFTER_MEDIA_SETTINGS = 20;

	private $isDetectionEnabled;

	public function __construct( bool $isDetectionEnabled ) {
		$this->isDetectionEnabled = $isDetectionEnabled;
	}

	public function add_hooks() {
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueueAppScript' ] );
		add_action( 'icl_tm_menu_mcsetup', [ $this, 'insertSettingSection' ], self::PRIORITY_AFTER_MEDIA_SETTINGS );
		add_filter( 'wpml_mcsetup_navigation_links', [ $this, 'insertMenuElement' ], self::PRIORITY_AFTER_MEDIA_SETTINGS );
		add_action( 'wpml_st_before_localization_ui_table', [ $this, 'showLocalizationUINotice' ] );
	}

	const SETTINGS_CAPABILITIES = [ 'wpml_manage_string_translation', 'manage_translations' ];

	public static function userCanReadSettings() {
		foreach ( self::SETTINGS_CAPABILITIES as $capability ) {
			if ( current_user_can( $capability ) ) {
				return true;
			}
		}

		return false;
	}

	public function enqueueAppScript( $hookSuffix ) {
		if ( 'wpml_page_tm/menu/settings' === $hookSuffix && self::userCanReadSettings() ) {
			$app = Resources::enqueueApp( 'wpml-st-settings' );
			$app( [
				'name' => 'wpmlSTSettings',
				'data' => [
					'restEndpoint' => trailingslashit( get_rest_url() ) . trailingslashit( Base::NAMESPACE ) . StringSettingsApiController::ROUTE,
					'nonce'        => wp_create_nonce( 'wp_rest' ),
				],
			] );
		}
	}

	public function insertSettingSection() {
		$rootStateClass = $this->isDetectionEnabled ? 'on' : 'off';

		?>
		<div class="wpml-section" id="<?php echo esc_attr( self::SECTION_ID ); ?>">
			<div class="wpml-section-content wpml-section-content-wide">
				<div class="wpml-settings-list">
					<div role="presentation">
						<ul class="settings-ul">
							<li aria-label="<?php echo esc_attr( self::ANCHOR_ID ); ?>" id="<?php echo esc_attr( self::ANCHOR_ID ); ?>" class="setting-item <?php echo esc_attr( $rootStateClass ); ?>">
								<div id="toggle-detect-js-string-spinner" style="display: none">
									<span class="detect-js-string-spinner"></span>
								</div>
								<div class="setting-item-title">
									<span class="setting-item-title-label">
										<?php esc_html_e( 'Detect strings in JavaScript files', 'wpml-string-translation' ); ?>
									</span>
									<span class="setting-item-title-sublabel">
										<?php
											echo sprintf(
												/* translators: Description under the setting that turns on scanning of JavaScript files, on the WPML settings page. %1$s: opening link tag, %2$s: closing link tag; the words between them become a link to the Admin Text Translation page. */
												esc_html__( 'When enabled, WPML tracks JavaScript files loaded on your site\'s pages. Texts (strings) from these files will be included when you scan a theme or plugin in %1$sAdmin Texts Translation%2$s.', 'wpml-string-translation' ),
												'<a href="' . esc_url( admin_url( 'admin.php?page=wpml-admin-texts-translation' ) ) . '">',
												'</a>'
											);
										?>
									</span>
								</div>
								<label for="toggle-detect-js-string" class="wpml-on-off-switch gray-dark">
									<input id="toggle-detect-js-string" aria-labelledby="toggle-detect-js-string" type="checkbox" <?php if ( $this->isDetectionEnabled ): ?>checked="checked" <?php endif; ?>/>
									<span aria-hidden="false" class="on"><?php esc_html_e( 'ON', 'sitepress' ); ?></span>
									<span aria-hidden="true" class="off"><?php esc_html_e( 'OFF', 'sitepress' ); ?></span>
									<span class="visually-hidden"></span>
								</label>
							</li>
						</ul>

						<div id="" class="warning notice-warning otgs-notice wpml-settings-list-notice">
							<p><?php
								echo sprintf(
									/* translators: Warning under the setting that turns on scanning of JavaScript files, on the WPML settings page. %1$s: opening bold tag, %2$s: closing bold tag, around the word "Note:". */
									esc_html__( '%1$sNote:%2$s This feature may affect site performance. We recommend disabling it after scanning is complete.', 'wpml-string-translation' ),
									'<b>',
									'</b>'
								);
								?>
							</p>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	public function insertMenuElement( array $sections ) {
		$sections[ self::SECTION_ID ] = $this->getSectionTitle();

		return $sections;
	}

	private function getSectionTitle() {
		/* translators: Name of the String Translation page in the WPML menu, the heading of that page, and the title of its section on the WPML settings page. */
		return esc_html__( 'String Translation', 'wpml-string-translation' );
	}

	public function showLocalizationUINotice() {
		$urlToSettings = admin_url( 'admin.php?page=tm/menu/settings&section=string-translation' );

		if ( $this->isDetectionEnabled ) {
			$text = sprintf(
				/* translators: Notice shown while scanning of JavaScript files is switched on. %1$s: opening link tag, %2$s: closing link tag; the words between them become a link to the WPML settings page. */
				esc_html__( 'JavaScript file scanning is active and may affect performance. %1$sDisable this feature%2$s in WPML Settings once your texts (strings) are registered.', 'wpml-string-translation' ),
				'<a href="'. $urlToSettings . '">',
				'</a>'
			);
		} else {
			$text = sprintf(
				/* translators: Notice on the Theme and plugins localization page after a scan found nothing. %1$s: opening link tag, %2$s: closing link tag; the words between them become a link to the WPML settings page. */
				esc_html__( 'Missing texts (strings) after scanning? %1$sEnable Scan strings in JavaScript files%2$s in WPML Settings, visit the page containing the string, then scan again.', 'wpml-string-translation' ),
				'<a href="'. $urlToSettings . '">',
				'</a>'
			);
		}

		?>
		<div class="warning-content-wrap">
			<div class="warning-content">
				<p><?php echo $text; ?></p>
			</div>
		</div>
		<?php
	}

	public static function getSettingsURL(): string {
		if ( ! defined( 'WPML_TM_FOLDER' ) ) {
			return UIPage::getSettings() . '#' . self::SECTION_ID;
		}

		return UIPage::getSettings()
			. '&section=' . self::SETTINGS_SECTION
			. '&flash=' . rawurlencode( self::ANCHOR_ID )
			. '#' . self::ANCHOR_ID;
	}
}
