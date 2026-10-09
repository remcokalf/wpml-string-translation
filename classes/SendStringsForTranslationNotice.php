<?php

namespace WPML\ST\AdminTexts;

use WPML\ST\StringTranslationPage;
use WPML_WP_API;

class SendStringsForTranslationNotice {

	public static function init() {

		$wp_api = new WPML_WP_API();

		if ( current_user_can( 'manage_options' ) && $wp_api->is_string_translation_page() ) {
			$notices  = wpml_get_admin_notices();
			$noticeId = 'SendStringsForTranslationNotice';

			$linkHref   = admin_url( 'admin.php?page=tm%2Fmenu%2Fmain.php' );
			$noticeText = sprintf(
				/* translators: Notice on the String Translation screen. %1$s: opening link tag, %2$s: closing link tag; the words between them become a link to the Translation Dashboard, which is the name of that screen in the WPML menu. */
				__( 'To translate strings automatically, by your translators or a translation service, use the %1$sTranslation Dashboard%2$s.', 'wpml-string-translation' ),
				'<a href="' . $linkHref . '" target="_blank">',
				'</a>'
			);
			$notice = $notices->get_new_notice( $noticeId, $noticeText )->set_css_class_types( 'info' );
			$notice->set_css_classes(['send-strings-for-translation-notice']);
			$notice->set_dismissible( true );
			$notice->add_display_callback( [ StringTranslationPage::class, 'isCurrent' ] );
			$notices->add_notice( $notice );
		}

	}

}
