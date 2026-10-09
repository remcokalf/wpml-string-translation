<?php

class WPML_Package_ST {

	public function get_string_element( $string_id, $column = false ) {
		global $wpdb;

		$result          = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}icl_strings WHERE id=%d", array( $string_id ) ) );

		if ( $result && $column && isset( $result[ $column ] ) ) {
			$result = $result[ $column ];
		}

		return $result;
	}

	public function get_string_title( $title, $string_details ) {
		$string_title = $this->get_string_element( $string_details['string_id'], 'title' );
		if ( $string_title ) {
			return $string_title;
		} else {
			return $title;
		}
	}

}
