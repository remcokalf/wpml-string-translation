<?php

use WPML\API\Sanitize;

class WPML_ST_Slug_Translation_UI_Save implements IWPML_Action {

	const ACTION_HOOK_FOR_POST = 'wpml_save_cpt_sync_settings';
	const ACTION_HOOK_FOR_TAX  = 'wpml_save_taxonomy_sync_settings';

	const NOTICE_GROUP = 'wpml-st-slug-original-language';

	private $settings;

	private $records;

	private $sitepress;

	private $wp_element_type;

	private $action_hook;

	public function __construct(
		WPML_ST_Slug_Translation_Settings $settings,
		WPML_Slug_Translation_Records $records,
		SitePress $sitepress,
		IWPML_WP_Element_Type $wp_element_type,
		$action_hook
	) {
		$this->settings        = $settings;
		$this->records         = $records;
		$this->sitepress       = $sitepress;
		$this->wp_element_type = $wp_element_type;
		$this->action_hook     = $action_hook;
	}

	public function add_hooks() {
		add_action( $this->action_hook, array( $this, 'save_element_type_slug_translation_options' ), 1 );
	}

	public function save_element_type_slug_translation_options() {
		if ( function_exists( 'wpml_get_admin_notices' ) ) {
			wpml_get_admin_notices()->remove_notice_group( self::NOTICE_GROUP );
		}
		if ( $this->settings->is_enabled() && ! empty( $_POST['translate_slugs'] ) ) {

			foreach ( $_POST['translate_slugs'] as $type => $data ) {
				$type            = (string) Sanitize::string( $type );
				$data            = $this->sanitize_translate_slug_data( $data );
				$is_type_enabled = $this->has_translation( $data );
				$this->settings->set_type( $type, $is_type_enabled );
				$this->update_slug_translations( $type, $data );
			}

			$this->settings->save();
		}
	}

	private function sanitize_translate_slug_data( array $data ) {
		$data['original'] = Sanitize::stringProp( 'original', $data );

		foreach ( $data['langs'] as $lang => $translated_slug ) {
			$data['langs'][ $lang ] = Sanitize::string( $translated_slug );
		}

		return $data;
	}

	private function has_translation( array $data ) {
		$slug_translations = $this->get_slug_translations( $data );

		foreach ( $slug_translations as $slug ) {
			if ( trim( $slug ) ) {
				return true;
			}
		}

		return false;
	}

	private function get_slug_translations( array $data ) {
		$slugs              = $data['langs'];
		$original_slug_lang = $data['original'];
		unset( $slugs[ $original_slug_lang ] );
		return $slugs;
	}

	private function update_slug_translations( $type, array $data ) {
		$string = $this->records->get_slug_string( $type );

		if ( ! $string ) {
			$string = $this->register_string_if_not_exit( $type );
		}

		if ( $string ) {

			$original_lang = $this->sitepress->get_default_language();

			if ( isset( $data['original'] ) ) {
				$original_lang = $data['original'];
			}

			if ( $string->get_language() !== $original_lang ) {
				$valid = \WPML\Language\RequestedLanguage::validate( $original_lang, \WPML\Language\RequestedLanguage::SCOPE_CONFIGURED );

				if ( null === $valid ) {
					$this->refuse_original_language( $type, $original_lang );
					$original_lang = $string->get_language();
				} else {
					$original_lang = $valid;
					$string->set_language( $original_lang );
				}
			}

			if ( isset( $data['langs'] ) ) {

				foreach ( $this->sitepress->get_active_languages() as $code => $lang ) {

					if ( $code !== $original_lang ) {
						$posted_slug       = isset( $data['langs'][ $code ] ) ? $data['langs'][ $code ] : '';
						$translation_value = $this->sanitize_slug( $posted_slug );
						$translation_value = urldecode( $translation_value );

						if ( '' === $translation_value ) {
							$string->set_translation( $code, '', ICL_TM_NOT_TRANSLATED );
						} else {
							$string->set_translation( $code, $translation_value, ICL_TM_COMPLETE );
						}
					}
				}
			}

			$string->update_status();
		}
	}

	private function refuse_original_language( $type, $lang ) {
		if ( ! function_exists( 'wpml_get_admin_notices' ) ) {
			return;
		}

		$text = sprintf(
			/* translators: Error shown on the WPML settings page after saving slug translations. %1$s: the content type, for example "product". %2$s: the language code that was refused, for example "hi". */
			__( 'The original language of the "%1$s" slug was not changed: "%2$s" is not one of this site\'s languages.', 'wpml-string-translation' ),
			esc_html( $type ),
			esc_html( $lang )
		);

		$notices = wpml_get_admin_notices();
		$notice  = $notices->create_notice( 'refused-' . $type, $text, self::NOTICE_GROUP );
		$notice->set_css_class_types( 'error' );
		$notice->set_dismissible( true );
		$notices->add_notice( $notice, true );
	}

	private function register_string_if_not_exit( $type ) {
		$slug = $this->get_registered_slug( $type );
		$this->records->register_slug( $type, $slug );
		return $this->records->get_slug_string( $type );
	}

	private function sanitize_slug( $slug ) {
		return implode( '/', array_map( array( 'WPML_Slug_Translation', 'sanitize' ), explode( '/', $slug ) ) );
	}

	private function get_registered_slug( $type_name ) {
		$wp_element = $this->wp_element_type->get_wp_element_type_object( $type_name );
		return $wp_element ? trim( $wp_element->rewrite['slug'], '/' ) : false;
	}
}
