<?php


class WPML_ST_String_Statuses {

	public static function get_status( $status ) {
		switch ( $status ) {
			case ICL_STRING_TRANSLATION_COMPLETE:
				/* translators: Status value on the String Translation page: every language of the text has a translation. */
				return __( 'Translation complete', 'wpml-string-translation' );

			case ICL_STRING_TRANSLATION_PARTIAL:
				/* translators: Status value on the String Translation page: some languages of the text have a translation and some do not. */
				return __( 'Partial translation', 'wpml-string-translation' );

			case ICL_STRING_TRANSLATION_NEEDS_UPDATE:
				return __( 'Translation needs update', 'wpml-string-translation' );

			case ICL_STRING_TRANSLATION_NOT_TRANSLATED:
				/* translators: Status value on the String Translation and Packages pages: the text has no translation yet. */
				return __( 'Not translated', 'wpml-string-translation' );

			case ICL_STRING_TRANSLATION_WAITING_FOR_TRANSLATOR:
				return __( 'Waiting for translator', 'wpml-string-translation' );

		}

		return '';
	}

}
