<?php

class WPML_ST_Word_Count_Package_Records {

	private $wpdb;

	public function __construct( wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	public function get_all_package_ids() {
		$wpdb = $this->wpdb;

		return array_map(
			'intval',
			$wpdb->get_col( "SELECT ID FROM {$wpdb->prefix}icl_string_packages" )
		);
	}

	public function get_packages_ids_without_word_count() {
		$wpdb = $this->wpdb;

		return array_map(
			'intval',
			$wpdb->get_col(
				"SELECT ID FROM {$wpdb->prefix}icl_string_packages WHERE word_count IS NULL"
			)
		);
	}

	public function get_word_counts( $post_id ) {
		$wpdb = $this->wpdb;

		return $wpdb->get_col(
			$wpdb->prepare(
				"SELECT word_count FROM {$wpdb->prefix}icl_string_packages WHERE post_id = %d",
				$post_id
			)
		);
	}

	public function set_word_count( $package_id, $word_count ) {
		$this->wpdb->update(
			$this->wpdb->prefix . 'icl_string_packages',
			array( 'word_count' => $word_count ),
			array( 'ID' => $package_id )
		);
	}

	public function get_word_count( $package_id ) {
		$wpdb = $this->wpdb;

		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT word_count FROM {$wpdb->prefix}icl_string_packages WHERE ID = %d",
				$package_id
			)
		);
	}

	public function reset_all( array $package_kinds ) {
		if ( ! $package_kinds ) {
			return;
		}

		$wpdb = $this->wpdb;
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}icl_string_packages SET word_count = NULL
				WHERE kind_slug IN(" . implode( ', ', array_fill( 0, count( $package_kinds ), '%s' ) ) . ')',
				$package_kinds
			)
		);
	}

	public function get_ids_from_kind_slugs( array $kinds ) {
		if ( ! $kinds ) {
			return array();
		}

		$wpdb = $this->wpdb;

		return array_map(
			'intval',
			$wpdb->get_col(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->prefix}icl_string_packages
					WHERE kind_slug IN(" . implode( ', ', array_fill( 0, count( $kinds ), '%s' ) ) . ')',
					$kinds
				)
			)
		);
	}

	public function get_ids_from_post_types( array $post_types ) {
		if ( ! $post_types ) {
			return array();
		}

		$wpdb = $this->wpdb;

		return array_map(
			'intval',
			$wpdb->get_col(
				$wpdb->prepare(
					"SELECT sp.ID FROM {$wpdb->prefix}icl_string_packages AS sp
					LEFT JOIN {$wpdb->posts} AS p ON p.ID = sp.post_id
					WHERE p.post_type IN(" . implode( ', ', array_fill( 0, count( $post_types ), '%s' ) ) . ')',
					$post_types
				)
			)
		);
	}

	public function count_items_by_kind_not_part_of_posts( $kind_slug ) {
		$wpdb = $this->wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}icl_string_packages
				 WHERE kind_slug = %s AND post_id IS NULL",
				$kind_slug
			)
		);
	}

	public function count_word_counts_by_kind( $kind_slug ) {
		$wpdb = $this->wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}icl_string_packages
				 WHERE kind_slug = %s AND word_count IS NOT NULL
				 AND post_id IS NULL",
				$kind_slug
			)
		);
	}

	public function get_word_counts_by_kind( $kind_slug ) {
		$wpdb = $this->wpdb;

		return $wpdb->get_col(
			$wpdb->prepare(
				"SELECT word_count FROM {$wpdb->prefix}icl_string_packages
				 WHERE kind_slug = %s",
				$kind_slug
			)
		);
	}
}
