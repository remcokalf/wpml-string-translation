<?php

namespace WP_CLI\Fetchers;

use WP_Comment;

class Comment extends Base {

	protected $msg = 'Could not find the comment with ID %d.';

	public function get( $arg ) {
		$comment_id = (int) $arg;
		$comment    = get_comment( $comment_id );

		if ( null === $comment ) {
			return false;
		}

		return $comment;
	}
}
