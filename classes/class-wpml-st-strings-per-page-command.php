<?php

class WPML_ST_Strings_Per_Page_Command {

	const ACTION      = 'wpml_st_strings_per_page';
	const PARAM       = 'strings_per_page';
	const REDIRECT_TO = 'redirect_to';

	public static function register() {
		\WPML\Request\Adapter\AdminPost::register(
			self::ACTION,
			\WPML\Request\Policy\Policy::capability(
				[ 'wpml_manage_string_translation', 'manage_translations' ],
				\WPML\Request\Policy\Authenticity::actionNonce( self::ACTION )
			),
			[ self::class, 'handle' ]
		);
	}

	public static function url( $redirectTo ) {
		return wp_nonce_url(
			add_query_arg(
				[
					'action'          => self::ACTION,
					self::REDIRECT_TO => rawurlencode( (string) $redirectTo ),
				],
				admin_url( 'admin-post.php' )
			),
			self::ACTION
		);
	}

	public static function handle() {
		global $sitepress, $sitepress_settings;

		$perPage    = isset( $_GET[ self::PARAM ] ) ? (int) $_GET[ self::PARAM ] : 0;
		$redirectTo = isset( $_GET[ self::REDIRECT_TO ] ) && is_string( $_GET[ self::REDIRECT_TO ] ) ? esc_url_raw( wp_unslash( $_GET[ self::REDIRECT_TO ] ) ) : '';

		if ( $perPage > 0 && $sitepress ) {
			$sitepress_settings['st']['strings_per_page'] = $perPage;
			$sitepress->save_settings( $sitepress_settings );
		}

		static::redirect( wp_validate_redirect( $redirectTo, admin_url( 'admin.php?page=' . WPML_ST_FOLDER . '/menu/string-translation.php' ) ) );
	}

	protected static function redirect( $url ) {
		wp_safe_redirect( $url, 302, 'WPML' );
		exit;
	}
}
