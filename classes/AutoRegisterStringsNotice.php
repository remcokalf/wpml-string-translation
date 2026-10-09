<?php

namespace WPML\ST;

use WPML\ST\Gettext\AutoRegisterSettings;
use WPML_WP_API;
use function WPML\Container\make;

class AutoRegisterStringsNotice {

	public static function init() {
		$wp_api = new WPML_WP_API();
		if ( current_user_can( 'manage_options' ) && $wp_api->is_string_translation_page() ) {
			$autoRegisterDisabled = make( AutoRegisterSettings::class )->getIsTypeDisabled();
			$notices              = wpml_get_admin_notices();
			$noticeId             = 'AutoRegisterStringsNotice';

			if ( $autoRegisterDisabled ) {
				$linkHref   = ! empty( $_GET['trop'] )
					? admin_url( 'admin.php?page=tm/menu/main.php&tab=strings#dashboard_wpml_st_autoregister' )
					: '#dashboard_wpml_st_autoregister';
				$noticeText = sprintf(
					/* translators: Notice on the WPML screens when automatic string registration is off. %1$s: opening link tag, %2$s: closing link tag; the words between them become the link. */
					__( 'String auto registration is disabled. %1$sClick here to enable it%2$s', 'wpml-string-translation' ),
					'<a href="' . esc_url( $linkHref ) . '" id="wpml_open_autoregistration_setting">',
					'</a>'
				);
				$notice     = $notices->get_new_notice(
					$noticeId, $noticeText
				)->set_css_class_types( 'warning' );
				$notice->set_dismissible( true );
				$notice->add_display_callback( [ StringTranslationPage::class, 'isCurrent' ] );
				$notices->add_notice( $notice );
			} elseif ( ! $autoRegisterDisabled && ! is_null( $notices->get_notice( $noticeId ) ) ) {
				$notices->remove_notice( $notices::DEFAULT_GROUP, $noticeId );
			}
		}
	}

}
