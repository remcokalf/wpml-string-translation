<?php

namespace WP_CLI\Fetchers;

use WP_Post;

class Post extends Base {

	protected $msg = 'Could not find the post with ID %d.';

	public function get( $arg ) {
		$post = get_post( $arg );

		if ( null === $post ) {
			return false;
		}

		return $post;
	}
}
