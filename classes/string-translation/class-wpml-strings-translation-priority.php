<?php

class WPML_Strings_Translation_Priority {
	private $wpdb;

	public function __construct( wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	public function change_translation_priority_of_strings( $strings, $priority ) {
		if ( ! $strings ) {
			return;
		}

		$wpdb = $this->wpdb;
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}icl_strings SET translation_priority=%s WHERE id IN (" . implode( ', ', array_fill( 0, count( $strings ), '%d' ) ) . ')',
				array_merge( array( $priority ), array_map( 'intval', $strings ) )
			)
		);
	}
}
