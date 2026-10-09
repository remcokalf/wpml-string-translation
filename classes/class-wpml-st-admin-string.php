<?php

class WPML_ST_Admin_String extends WPML_ST_String {

	private $name;

	private $value;

	public function update_value( $new_value ) {
		$this->fetch_name_and_value();
		if ( md5( $this->value ) !== $this->name ) {
			$this->value = $new_value;
			$this->set_property( 'value', $new_value );
			$this->update_status();
		}
	}

	private function fetch_name_and_value() {
		if ( is_null( $this->name ) || is_null( $this->value ) ) {
			$wpdb = $this->wpdb;
			$res  = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT name, value FROM {$wpdb->prefix}icl_strings WHERE id = %d",
					$this->string_id()
				)
			);
			$this->name  = $res->name;
			$this->value = $res->value;
		}
	}
}
