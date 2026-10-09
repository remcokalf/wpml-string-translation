<?php

abstract class WPML_ST_String_Positions {

	const TEMPLATE_PATH = '/templates/string-tracking/';

	protected $string_position_mapper;

	protected $template_service;

	public function __construct(
		WPML_ST_DB_Mappers_String_Positions $string_position_mapper,
		IWPML_Template_Service $template_service
	) {
		$this->string_position_mapper = $string_position_mapper;
		$this->template_service       = $template_service;
	}

	abstract protected function get_model( $string_id );

	abstract protected function get_template_name();

	public function dialog_render( $string_id ) {
		echo $this->get_template_service()->show( $this->get_model( $string_id ), $this->get_template_name() );
	}

	protected function get_mapper() {
		return $this->string_position_mapper;
	}

	protected function get_template_service() {
		return $this->template_service;
	}
}