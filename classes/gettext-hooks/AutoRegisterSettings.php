<?php

namespace WPML\ST\Gettext;

use wpdb;
use WPML\FP\Obj;
use WPML\ST\Package\Domains;
use function wpml_collect;
use WPML_ST_Settings;
use WPML\ST\TranslationFile\StringCollation;

class AutoRegisterSettings {
	use StringCollation;

	const KEY_EXCLUDED_DOMAINS = 'wpml_st_auto_reg_excluded_contexts';

	const KEY_ENABLED = 'auto_register_enabled';

	protected $wpdb;

	private $settings;

	private $package_domains;

	private $localization;

	private $excluded_domains;

	public function __construct(
		wpdb $wpdb,
		WPML_ST_Settings $settings,
		Domains $package_domains,
		\WPML_Localization $localization
	) {
		$this->wpdb            = $wpdb;
		$this->settings        = $settings;
		$this->package_domains = $package_domains;
		$this->localization    = $localization;
	}

	public function getIsTypeOnlyViewedByAdmin() {
		return apply_filters( 'wpml_st_get_setting', 'isAutoregisterStringsTypeOnlyViewedByAdmin' );
	}

	public function getIsTypeViewedByAllUsers() {
		return apply_filters( 'wpml_st_get_setting', 'isAutoregisterStringsTypeViewedByAllUsers' );
	}

	public function getIsTypeDisabled() {
		return apply_filters( 'wpml_st_get_setting', 'isAutoregisterStringsTypeDisabled' );
	}

	public function getTypeOnlyViewedByAdmin() {
		return apply_filters( 'wpml_st_get_setting', 'autoregisterStringsTypeOnlyViewedByAdmin' );
	}

	public function getTypeViewedByAllUsers() {
		return apply_filters( 'wpml_st_get_setting', 'autoregisterStringsTypeViewedByAllUsers' );
	}

	public function getTypeDisabled() {
		return apply_filters( 'wpml_st_get_setting', 'autoregisterStringsTypeDisabled' );
	}

	public function getShouldRegisterBackendStrings() {
		return apply_filters( 'wpml_st_get_setting', 'shouldRegisterBackendStrings' );
	}

	public function isEnabled() {
		return $this->getIsTypeOnlyViewedByAdmin() || $this->getIsTypeViewedByAllUsers();
	}

	private function getSetting( $key, $default = null ) {
		$setting = $this->settings->get_setting( $key );
		return null !== $setting ? $setting : $default;
	}

	public function getExcludedDomains() {
		if ( ! $this->excluded_domains ) {
			$excluded               = $this->getSetting( self::KEY_EXCLUDED_DOMAINS, [] );
			$this->excluded_domains = wpml_collect( $excluded )
				->reject( [ $this, 'isAdminOrPackageDomain' ] )
				->toArray();
		}

		return $this->excluded_domains;
	}

	public function isExcludedDomain( $domain ) {
		return in_array( $domain, $this->getExcludedDomains(), true );
	}

	public function get_included_contexts() {
		return array_values( array_diff( $this->getAllDomains(), $this->getExcludedDomains() ) );
	}

	public function getAllDomains() {
		$wpdb = $this->wpdb;

		return wpml_collect(
			$wpdb->get_col(
				sprintf(
					"SELECT DISTINCT context %s
					FROM {$wpdb->prefix}icl_strings",
					esc_sql( $this->getCollateForContextColumn( $wpdb ) )
				)
			)
		)
			->reject( [ $this, 'isAdminOrPackageDomain' ] )
			->merge( wpml_collect( $this->getExcludedDomains() ) )
			->unique()
			->toArray();
	}

	public function isAdminOrPackageDomain( $domain ) {
		if ( ! is_string( $domain ) || $domain === '' ) {
			return false;
		}

		return 0 === strpos( $domain, \WPML_Admin_Texts::DOMAIN_NAME_PREFIX )
			   || $this->package_domains->isPackage( $domain );
	}

	public function getDomainsAndTheirExcludeStatus() {
		$contexts = $this->getAllDomains();
		$excluded = $this->getExcludedDomains();

		$result = array();
		foreach ( $contexts as $context ) {
			$result[ $context ] = in_array( $context, $excluded );
		}

		return $result;
	}

	public function saveExcludedContexts() {
		if ( ! current_user_can( 'manage_options' ) ) {
			/* translators: Error message shown when the user does not have the rights to do what they asked for. Past participle used as a state, lower case in the source. */
			wp_send_json_error( __( 'not allowed', 'wpml-string-translation' ) );
			return;
		}

		$nonce    = isset( $_POST['nonce'] ) ? $_POST['nonce'] : '';
		$is_valid = wp_verify_nonce( $nonce, 'wpml-st-cancel-button' );

		if ( $is_valid ) {
			$excluded_contexts = [];

			if ( isset( $_POST[ self::KEY_EXCLUDED_DOMAINS ] ) && is_array( $_POST[ self::KEY_EXCLUDED_DOMAINS ] ) ) {
				$excluded_contexts = array_map( 'stripslashes', $_POST[ self::KEY_EXCLUDED_DOMAINS ] );
			}

			$this->settings->update_setting( self::KEY_EXCLUDED_DOMAINS, $excluded_contexts, true );

			wp_send_json_success();
		} else {
			wp_send_json_error( __( 'Nonce value is invalid', 'wpml-string-translation' ) );
		}
	}

	public function getFeatureEnabledDescription() {
		return '<span class="icon otgs-ico-info-o"></span> '
			/* translators: Note on the String Translation page under the automatic string registration setting. %s: how much time is left, counted down in the page as hours, minutes and seconds. */
			. __( "Automatic string registration will remain active for <span class='counter-msg'>%s</span>. Please visit the site's front-end to allow WPML to find strings for translation.", 'wpml-string-translation' );
	}

	public function getFeatureDisabledDescription() {
		/* translators: Note on the String Translation page under the automatic string registration setting, shown while the setting is off. "It" is that setting. */
		return __( '* This feature is only intended for sites that are in development. It will significantly slow down the site, but help you find strings that WPML cannot detect in the PHP code.', 'wpml-string-translation' );
	}

	public function getDomainsWithStringsTranslationData() {
		$excluded = $this->getExcludedDomains();
		$domains  = wpml_collect( $this->getAllDomains() )->merge( $excluded )->unique()->toArray();
		$stats    = $this->localization->get_domain_stats( $domains, 'default', false, true );

		$result = [];
		foreach ( $domains as $domain ) {
			$completed_strings_count  = (int) Obj::path( [ $domain, 'complete' ], $stats );
			$incomplete_strings_count = (int) Obj::path( [ $domain, 'incomplete' ], $stats );

			$result[ $domain ] = [
				'name'                     => $domain,
				'translated_strings_count' => $completed_strings_count,
				'total_strings_count'      => $incomplete_strings_count + $completed_strings_count,
				'is_blocked'               => in_array( $domain, $excluded ),
			];
		}

		return $result;
	}

}
