<?php

class WPML_ST_ICL_Strings extends WPML_WPDB_User {

	private $string_id = 0;

	public function __construct( &$wpdb, $string_id ) {
		parent::__construct( $wpdb );
		$string_id = (int) $string_id;
		if ( $string_id > 0 ) {
			$this->string_id = $string_id;
		} else {
			throw new InvalidArgumentException( 'Invalid String ID: ' . $string_id );
		}
	}

	public function update( $args ) {
		$this->wpdb->update(
			$this->wpdb->prefix . 'icl_strings',
			$args,
			array( 'id' => $this->string_id )
		);

		return $this;
	}

	public function value() {
		$wpdb = $this->wpdb;

		return $wpdb->get_var(
			$wpdb->prepare(
				" SELECT value
									FROM {$wpdb->prefix}icl_strings
									WHERE id = %d LIMIT 1",
				$this->string_id
			)
		);
	}

	public function language() {
		$wpdb = $this->wpdb;

		return $wpdb->get_var(
			$wpdb->prepare(
				" SELECT language
									FROM {$wpdb->prefix}icl_strings
									WHERE id = %d LIMIT 1",
				$this->string_id
			)
		);
	}

	public function status() {
		$wpdb = $this->wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				" SELECT status
									FROM {$wpdb->prefix}icl_strings
									WHERE id = %d LIMIT 1",
				$this->string_id
			)
		);
	}
}
