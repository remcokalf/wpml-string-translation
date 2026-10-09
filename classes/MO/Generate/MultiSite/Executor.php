<?php

namespace WPML\ST\MO\Generate\MultiSite;


class Executor {

	const MAIN_SITE_ID = 1;

	public function withEach( $callback ) {
		$applyCallback = function( $siteId ) use ( $callback ) {
			switch_to_blog( $siteId );

			return [ $siteId, $callback() ];
		};

		$initialBlogId = get_current_blog_id();
		$result = $this->getSiteIds()->map( $applyCallback );
		switch_to_blog( $initialBlogId );

		return $result;

	}

	public function getSiteIds() {
		return \wpml_collect( get_sites( [ 'number' => PHP_INT_MAX  ] ) )->pluck( 'id' );
	}

	public function executeWith( $siteId, callable $callback ) {
		switch_to_blog( $siteId );
		$result = $callback();
		restore_current_blog();

		return $result;
	}
}