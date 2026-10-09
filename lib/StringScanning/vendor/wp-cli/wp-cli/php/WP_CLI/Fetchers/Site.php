<?php

namespace WP_CLI\Fetchers;

class Site extends Base {

	protected $msg = 'Could not find the site with ID %d.';

	public function get( $site_id ) {
		return $this->get_site( $site_id );
	}

	private function get_site( $arg ) {
		global $wpdb;

		$site = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->blogs} WHERE blog_id = %d",
				$arg
			)
		);

		if ( ! empty( $site ) ) {
			return $site;
		}

		return false;
	}
}
