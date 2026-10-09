<?php

class WPML_PO_Import_Strings {

	const NONCE_NAME = 'wpml-po-import-strings';

	private $errors;

	private $sitepress;

	public function __construct( \SitePress $sitepress ) {
		$this->sitepress = $sitepress;
	}

	public static function is_review_render_request() {
		if ( ! array_key_exists( 'icl_po_upload', $_POST ) || ! isset( $_POST['_wpnonce'] ) ) {
			return false;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) );

		return (bool) wp_verify_nonce( $nonce, 'icl_po_form' );
	}

	public function maybe_import_po_add_strings() {
		if ( ! current_user_can( 'wpml_manage_string_translation' ) && ! current_user_can( 'manage_translations' ) ) {
			return;
		}

		if ( self::is_review_render_request() ) {
			add_filter( 'wpml_st_get_po_importer', array( $this, 'import_po' ) );
			return;
		}

		if ( array_key_exists( 'action', $_POST ) && 'icl_st_save_strings' === $_POST['action'] && isset( $_POST['_wpnonce'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'add_po_strings' ) ) {
			$this->add_strings();
		}
	}

	public function import_po() {
		if ( $_FILES[ 'icl_po_file' ][ 'size' ] === 0 ) {
			$this->errors = esc_html__( 'File upload error', 'wpml-string-translation' );
			return null;
		} else {
			$po_importer  = new WPML_PO_Import( $_FILES[ 'icl_po_file' ][ 'tmp_name' ] );
			$this->errors = $po_importer->get_errors();
			return $po_importer;
		}
	}

	public function get_errors() {
		return $this->errors;
	}

	private function add_strings() {
		$wpml_st_string_factory = WPML\Container\make( WPML_ST_String_Factory::class );
		$strings                = json_decode( $_POST['strings_json'] );
		$source_lang            = \WPML\StringTranslation\Infrastructure\TranslateEverything\EnglishSourceLanguage::normalize(
			(string) $this->get_filtered_source_lang(),
			array_map( 'strval', array_keys( (array) $this->sitepress->get_active_languages() ) ),
			(string) $this->sitepress->get_default_language()
		);

		foreach ( (array) $strings as $string ) {
			$original = WPML_Kses_Post::wp_kses_post_preserve_tags_format( $string->original );
			$context = isset( $string->context )
				? (string) \WPML\API\Sanitize::string( $string->context ) : '';

			$string->original = str_replace( '\n', "\n", $original );
			$name             = isset( $string->name )
				? (string) \WPML\API\Sanitize::string( $string->name ) : md5( $original );

			$string_id = icl_register_string( array(
				'domain'  => (string) \WPML\API\Sanitize::string( $_POST['icl_st_domain_name']),
				'context' => $context
			),
				$name,
				$original,
				false,
				$source_lang
			);

			if ( ! $string_id ) {
				continue;
			}

			$registered_string_lang = $wpml_st_string_factory->find_by_id( $string_id )->get_language();
			if ( $registered_string_lang !== $source_lang ) {
				$source_lang_details = $this->sitepress->get_language_details( $source_lang );
				$registered_string_lang_details = $this->sitepress->get_language_details( $registered_string_lang );
				$source_language_doc_url = \WPML\ST\OutboundLinks\OutboundLinks::to(
					'https://wpml.org/documentation/translating-your-contents/strings/how-to-change-the-source-language-of-strings/',
					array(
						'medium'   => 'notice',
						'campaign' => 'string-translation',
						'content'  => 'po-import',
					)
				);
				$this->errors = wpml_bold_names( sprintf(
					/* translators: Error on the String Translation page when the .po file being imported uses a different source language. %1$s: the language the texts are already registered in, %2$s: the language the user is importing them as, %3$s: opening link tag, %4$s: closing link tag. */
					__( 'You\'re trying to import strings that are already registered in %1$s. To import them as %2$s, first %3$schange the source language of existing strings%4$s using <b>String Translation</b>. Then, try importing them again.', 'wpml-string-translation' ),
					$registered_string_lang_details['display_name'] ?? $registered_string_lang,
					$source_lang_details['display_name'] ?? $source_lang,
					'<a target="_blank" class="external-link" href="' . esc_url( $source_language_doc_url ) . '">',
					'</a>'
				), array(
					'a' => array( 'href' => array(), 'target' => array(), 'class' => array() ),
				) );
				break;
			}

			$this->maybe_add_translation( $string_id, $string );
		}
	}

	private function maybe_add_translation( $string_id, $string ) {
		if ( $string_id && array_key_exists( 'icl_st_po_language', $_POST ) ) {
			if ( $string->translation !== '' ) {
				$status = ICL_TM_COMPLETE;
				if ( $string->fuzzy ) {
					$status = ICL_TM_NOT_TRANSLATED;
				}
				$translation = str_replace( '\n', "\n", wp_kses_post( $string->translation ) );

				icl_add_string_translation( $string_id, $_POST[ 'icl_st_po_language' ], $translation, $status );
				icl_update_string_status( $string_id );
			}
		}
	}

	protected function get_filtered_source_lang(): string {
		return ! empty( $_POST['icl_st_po_source_language'] )
			? filter_input( INPUT_POST, 'icl_st_po_source_language', FILTER_SANITIZE_FULL_SPECIAL_CHARS )
			: '';
	}
}
