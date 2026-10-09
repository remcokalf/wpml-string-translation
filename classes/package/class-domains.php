<?php

namespace WPML\ST\Package;

use WPML\Collect\Support\Collection;

class Domains {

	private $wpdb;

	private $domains;

	public function __construct( \wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	public function isPackage( $domain ) {
		return $domain && $this->getDomains()->contains( $domain );
	}

	public function getDomains() {
		$wpdb = $this->wpdb;

		if ( ! $this->domains ) {
			$this->domains = wpml_collect(
				$this->wpdb->get_col(
					"SELECT CONCAT(kind_slug, '-', name) FROM {$wpdb->prefix}icl_string_packages"
				)
			);
		}

		return $this->domains;
	}
}
