<?php

class WPML_ST_Element_Slug_Translation_UI {

	const TEMPLATE_FILE = 'slug-translation-ui.twig';

	private $model;

	private $template_service;

	public function __construct(
		WPML_ST_Element_Slug_Translation_UI_Model $model,
		IWPML_Template_Service $template_service
	) {
		$this->model            = $model;
		$this->template_service = $template_service;
	}

	public function init() {
		wp_enqueue_script(
			'wpml-custom-type-slug-ui',
			WPML_ST_URL . '/res/js/wpml-custom-type-slug-ui.js',
			array( 'jquery' ),
			WPML_ST_VERSION,
			true
		);

		return $this;
	}

	public function render( $type_name, $custom_type ) {
		$model = $this->model->get( $type_name, $custom_type );

		if ( ! $model ) {
			return '';
		}

		if ( ! empty( $model['has_missing_translations_message'] ) ) {
			$this->display_missing_translations_message( $model['has_missing_translations_message'] );
		}

		return $this->template_service->show( $model, self::TEMPLATE_FILE );
	}

	private function display_missing_translations_message( $message ) {
		$classes = 'instant-message message message-error icl-admin-instant-message icl-admin-message icl-admin-message-error';

		echo '<div class="' . $classes . '">' . $message . '</div>';
	}
}
