<?php

namespace WP_CLI;

use Mustangostang\Spyc;

class DocParser {

	protected $doc_comment;

	public function __construct( $doc_comment ) {
		$doc_comment       = str_replace( "\r\n", "\n", $doc_comment );
		$this->doc_comment = self::remove_decorations( $doc_comment );
	}

	private static function remove_decorations( $comment ) {
		$comment = preg_replace( '|^/\*\*[\r\n]+|', '', $comment );
		$comment = preg_replace( '|\n[\t ]*\*/$|', '', $comment );
		$comment = preg_replace( '|^[\t ]*\* ?|m', '', $comment );

		return $comment;
	}

	public function get_shortdesc() {
		if ( ! preg_match( '|^([^@][^\n]+)\n*|', $this->doc_comment, $matches ) ) {
			return '';
		}

		return $matches[1];
	}

	public function get_longdesc() {
		$shortdesc = $this->get_shortdesc();
		if ( ! $shortdesc ) {
			return '';
		}

		$longdesc = substr( $this->doc_comment, strlen( $shortdesc ) );

		$lines = [];
		foreach ( explode( "\n", $longdesc ) as $line ) {
			if ( 0 === strpos( $line, '@' ) ) {
				break;
			}

			$lines[] = $line;
		}

		return trim( implode( "\n", $lines ) );
	}

	public function get_tag( $name ) {
		if ( preg_match( '|^@' . $name . '\s+([a-z-_0-9]+)|m', $this->doc_comment, $matches ) ) {
			return $matches[1];
		}

		return '';
	}

	public function get_synopsis() {
		if ( ! preg_match( '|^@synopsis\s+(.+)|m', $this->doc_comment, $matches ) ) {
			return '';
		}

		return $matches[1];
	}

	public function get_arg_desc( $name ) {

		if ( preg_match( "/\[?<{$name}>.+\n: (.+?)(\n|$)/", $this->doc_comment, $matches ) ) {
			return $matches[1];
		}

		return '';
	}

	public function get_arg_args( $name ) {
		return $this->get_arg_or_param_args( "/^\[?<{$name}>.*/" );
	}

	public function get_param_desc( $key ) {

		if ( preg_match( "/\[?--{$key}=.+\n: (.+?)(\n|$)/", $this->doc_comment, $matches ) ) {
			return $matches[1];
		}

		return '';
	}

	public function get_param_args( $key ) {
		return $this->get_arg_or_param_args( "/^\[?--{$key}=.*/" );
	}

	private function get_arg_or_param_args( $regex ) {
		$bits       = explode( "\n", $this->doc_comment );
		$within_arg = false;
		$within_doc = false;
		$document   = [];
		foreach ( $bits as $bit ) {
			if ( preg_match( $regex, $bit ) ) {
				$within_arg = true;
			}

			if ( $within_arg && $within_doc && '---' === $bit ) {
				$within_doc = false;
			}

			if ( $within_arg && ! $within_doc && '---' === $bit ) {
				$within_doc = true;
			}

			if ( $within_doc ) {
				$document[] = $bit;
			}

			if ( $within_arg && '' === $bit ) {
				$within_arg = false;
				break;
			}
		}

		if ( $document ) {
			return Spyc::YAMLLoadString( implode( "\n", $document ) );
		}
		return null;
	}
}
