<?php

use WPML\FP\Fns;
use function WPML\FP\partial;

class WPML_ST_Slug_New_Match_Finder {
	public function get( $match, array $custom_types ) {
		$best_match = $this->find_the_best_match( $match, $this->map_to_new_matches( $match, $custom_types ) );
		if ( ! $best_match ) {
			$best_match = new WPML_ST_Slug_New_Match( $match, false );
		}

		return $best_match;
	}

	private function map_to_new_matches( $match, array $custom_types ) {
		return Fns::map( partial( [ $this, 'find_match_of_type' ], $match ), $custom_types );
	}

	public function find_match_of_type( $match, WPML_ST_Slug_Custom_Type $custom_type ) {
		if ( $custom_type->is_using_tags() ) {
			$slug             = $this->filter_slug_using_tag( $custom_type->get_slug() );
			$slug_translation = $this->filter_slug_using_tag( $custom_type->get_slug_translation() );

			$new_match = $this->adjust_match( $match, $slug, $slug_translation, true );

			$result = new WPML_ST_Slug_New_Match( $new_match, $custom_type->is_display_as_translated() );
		} else {
			$new_match = $this->adjust_match( $match, $custom_type->get_slug(), $custom_type->get_slug_translation() );
			$result    = new WPML_ST_Slug_New_Match(
				$new_match,
				$match !== $new_match && $custom_type->is_display_as_translated()
			);
		}

		return $result;
	}

	private function filter_slug_using_tag( $slug ) {
		$tag_patterns = [
			'%year%'     => '([0-9]{4})',
			'%monthnum%' => '([0-9]{1,2})',
			'%day%'      => '([0-9]{1,2})',
			'%hour%'     => '([0-9]{1,2})',
			'%minute%'   => '([0-9]{1,2})',
			'%second%'   => '([0-9]{1,2})',
			'%post_id%'  => '([0-9]+)',
		];

		foreach ( $tag_patterns as $tag => $pattern ) {
			$slug = preg_replace( '#' . $tag . '#', $pattern, $slug );
		}

		if ( preg_match( '#%([^/]+)%#', $slug ) ) {
			$slug = preg_replace( '#%[^/]+%#', '.+?', $slug );
		}

		if ( preg_match( '#\.\+\?#', $slug ) ) {
			$slug = preg_replace( '#\.\+\?#', '(.+?)', $slug );
		}

		return $slug;
	}

	private function adjust_match( $match, $slug, $slug_translation, $translation_is_pattern = false ) {
		if (
			! empty( $slug_translation )
			&& preg_match( '#^[^/]*/?\(?' . preg_quote( $slug ) . '\)?/#', $match )
			&& $slug !== $slug_translation
		) {
			$replacement = $translation_is_pattern ? $slug_translation : self::quote_metacharacters( $slug_translation );

			$replace = function( $match ) use ( $slug, $replacement ) {
				return str_replace( $slug, $replacement, $match[0] );
			};
			$match   = preg_replace_callback( '#^\(?' . preg_quote( addslashes( $slug ) ) . '\)?/#', $replace, $match );
		}

		return $match;
	}

	private static function quote_metacharacters( $value ) {
		return preg_replace( '#([.\\\\+*?\[\]^$(){}|])#', '\\\\$1', $value );
	}

	private function find_the_best_match( $match, $new_matches ) {
		$similarities = array();
		foreach ( $new_matches as $new_match ) {
			$percent = 0;
			similar_text( $match, $new_match->get_value(), $percent );
			$similarities[ intval( $percent * 100 ) ] = $new_match;
		}

		ksort( $similarities );

		return reset( $similarities );
	}
}
