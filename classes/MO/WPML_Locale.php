<?php

namespace WPML\ST\MO;

use WP_Locale;

class WPML_Locale extends WP_Locale {
	private $initialized = false;

	public function __construct() {

		$this->number_format = [
			'thousands_sep' => ',',
			'decimal_point' => '.',
		];

		add_action( 'in_admin_header', [ $this, 'make_sure_it_is_initialized_before_render' ] );
	}

	public function make_sure_it_is_initialized_before_render() {
		$this->lazy_init();
	}

	private function lazy_init() {
		if ( ! $this->initialized ) {
			$this->initialized = true;

			unset( $GLOBALS['text_direction'] );

			parent::__construct();
		}
	}

	public function get_weekday( $weekday_number ) {
		$this->lazy_init();

		return parent::get_weekday( $weekday_number );
	}

	public function get_weekday_initial( $weekday_name ) {
		$this->lazy_init();

		return parent::get_weekday_initial( $weekday_name );
	}

	public function get_weekday_abbrev( $weekday_name ) {
		$this->lazy_init();

		return parent::get_weekday_abbrev( $weekday_name );
	}

	public function get_month( $month_number ) {
		$this->lazy_init();

		return parent::get_month( $month_number );
	}

	public function get_month_abbrev( $month_name ) {
		$this->lazy_init();

		return parent::get_month_abbrev( $month_name );
	}

	public function get_month_genitive( $month_number ) {
		$this->lazy_init();

		return parent::get_month_genitive( $month_number );
	}

	public function get_meridiem( $meridiem ) {
		$this->lazy_init();

		return parent::get_meridiem( $meridiem );
	}

	public function is_rtl() {
		$this->lazy_init();

		return parent::is_rtl();
	}

	public function get_list_item_separator() {
		$this->lazy_init();

		return parent::get_list_item_separator();
	}

	public function get_word_count_type() {
		$this->lazy_init();

		return parent::get_word_count_type();
	}
}
