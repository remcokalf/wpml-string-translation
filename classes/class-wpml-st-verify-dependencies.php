<?php

class WPML_ST_Verify_Dependencies {

	const APP_PRODUCTION_ORIGIN = 'https://app.wpml.org';

	private $dependencies_file = null;

	public function verify_wpml( $wpml_core_version, ?string $dependenciesFilepath = null ) {
		if ( is_null( $dependenciesFilepath ) ) {
			$dependenciesFilepath = WPML_ST_PATH . '/wpml-dependencies.json';
		}

		if ( false === $wpml_core_version ) {
			add_action(
				'admin_notices',
				array(
					$this,
					'notice_no_wpml',
				)
			);
		} elseif ( ! WPML_Core_Version_Check::is_ok( $dependenciesFilepath ) ) {
			$this->dependencies_file = $dependenciesFilepath;
			add_action( 'admin_notices', array( $this, 'wpml_is_outdated' ) );
		}
	}

	function notice_no_wpml() {
		?>
		<div class="notice notice-error wpml-admin-notice wpml-st-inactive wpml-inactive">
			<p>
			<?php
			echo wp_kses(
				sprintf(
					/* translators: Admin notice shown when WPML String Translation is active but WPML core is missing. %1$s: the URL of the client's wpml.org account downloads page, which fills the first link. %2$s: the URL of this site's Plugins screen, which fills the second link. */
					__( 'WPML String Translation is active, but it needs WPML Multilingual CMS, which is not installed or not active. String Translation stays off until WPML runs. Download WPML from <a href="%1$s">your wpml.org account</a> and activate it on the <a href="%2$s">Plugins screen</a>.', 'wpml-string-translation' ),
					esc_url( self::account_downloads_url() ),
					esc_url( admin_url( 'plugins.php' ) )
				),
				array( 'a' => array( 'href' => array() ) )
			);
			?>
			</p>
		</div>
		<?php
	}

	function wpml_is_outdated() {
		?>
		<div class="notice notice-error wpml-admin-notice wpml-st-inactive wpml-outdated">
			<p>
			<?php
			echo wp_kses(
				sprintf(
					/* translators: Admin notice shown when the installed WPML core is older than WPML String Translation needs. %1$s: String Translation's own version. %2$s: the minimum WPML version it needs. %3$s: the WPML version this site runs. %4$s: the URL of this site's Plugins screen, which fills the link. */
					__( 'WPML String Translation %1$s needs WPML Multilingual CMS %2$s or newer. This site runs WPML %3$s, so String Translation stays off until WPML is updated. Update WPML on the <a href="%4$s">Plugins screen</a>.', 'wpml-string-translation' ),
					self::plugin_version(),
					self::required_core_version( $this->dependencies_file ),
					self::installed_core_version(),
					esc_url( admin_url( 'plugins.php' ) )
				),
				array( 'a' => array( 'href' => array() ) )
			);
			?>
			</p>
		</div>
		<?php
	}

	private static function account_downloads_url() {
		$path = '/account/downloads';

		if ( class_exists( '\WPML\OutboundLinks\OutboundLinks' ) ) {
			return \WPML\OutboundLinks\OutboundLinks::to(
				self::APP_PRODUCTION_ORIGIN . $path,
				array(
					'medium'   => 'notice',
					'campaign' => 'requirements',
				)
			);
		}

		return self::app_origin() . $path
			. '?utm_source=wpml-plugin&utm_medium=notice&utm_campaign=requirements';
	}

	private static function app_origin() {
		if ( ! defined( 'WPML_ORG_ORIGIN' ) || ! is_string( WPML_ORG_ORIGIN ) ) {
			return self::APP_PRODUCTION_ORIGIN;
		}

		$origin = rtrim( trim( (string) WPML_ORG_ORIGIN ), '/' );

		if ( '' === $origin ) {
			return self::APP_PRODUCTION_ORIGIN;
		}

		if ( ! preg_match( '#^https?://#i', $origin ) ) {
			$origin = 'https://' . $origin;
		}

		if ( ! preg_match( '#^(https?://)(.+)$#i', $origin, $matches ) ) {
			return self::APP_PRODUCTION_ORIGIN;
		}

		if ( 'wpml.org' === strtolower( $matches[2] ) ) {
			return self::APP_PRODUCTION_ORIGIN;
		}

		if ( 0 === strpos( strtolower( $matches[2] ), 'app.' ) ) {
			return $origin;
		}

		return $matches[1] . 'app.' . $matches[2];
	}

	private static function plugin_version() {
		static $version = null;

		if ( null !== $version ) {
			return $version;
		}

		$version = '';

		if ( function_exists( 'get_file_data' ) ) {
			$data    = get_file_data( WPML_ST_PATH . '/plugin.php', array( 'Version' => 'Version' ) );
			$version = isset( $data['Version'] ) ? (string) $data['Version'] : '';
		}

		return $version;
	}

	private static function required_core_version( $file = null ) {
		$file = $file ? $file : WPML_ST_PATH . '/wpml-dependencies.json';

		if ( ! is_readable( $file ) ) {
			return '';
		}

		$bundle = json_decode( (string) file_get_contents( $file ), true );

		return is_array( $bundle ) && isset( $bundle['sitepress-multilingual-cms'] )
			? (string) $bundle['sitepress-multilingual-cms']
			: '';
	}

	private static function installed_core_version() {
		return defined( 'ICL_SITEPRESS_VERSION' ) ? (string) ICL_SITEPRESS_VERSION : '';
	}
}
