<?php

class WPML_ST_Term_Link_Filter {

	const CACHE_GROUP = 'WPML_ST_Term_Link_Filter::replace_base_in_permalink_structure';

	private $sitepress;

	private $slug_records;

	private $cache_factory;

	private $tax_settings;

	public function __construct(
		WPML_Tax_Slug_Translation_Records $slug_records,
		SitePress $sitepress,
		WPML_WP_Cache_Factory $cache_factory,
		WPML_ST_Tax_Slug_Translation_Settings $tax_settings
	) {
		$this->slug_records  = $slug_records;
		$this->sitepress     = $sitepress;
		$this->cache_factory = $cache_factory;
		$this->tax_settings  = $tax_settings;
	}

	public function replace_slug_in_termlink( $termlink, $term ) {
		if ( ! $termlink || ! $this->sitepress->is_translated_taxonomy( $term->taxonomy ) ) {
			return $termlink;
		}

		$term_lang  = $this->sitepress->get_language_for_element( $term->term_taxonomy_id, 'tax_' . $term->taxonomy );
		$cache_key  = $termlink . $term_lang;
		$cache_item = $this->cache_factory->create_cache_item( self::CACHE_GROUP, $cache_key );

		if ( $cache_item->exists() ) {
			$termlink = $cache_item->get();
		} else {
			if ( $this->tax_settings->is_translated( $term->taxonomy ) ) {
				$original_slug   = $this->slug_records->get_original( $term->taxonomy );
				$translated_slug = $this->slug_records->get_translation( $term->taxonomy, $term_lang );

				if ( $original_slug && $translated_slug && $original_slug !== $translated_slug ) {
					$termlink = $this->replace_slug( $termlink, $original_slug, $translated_slug );
				}
			}

			$cache_item->set( $termlink );
		}

		return $termlink;
	}

	private function replace_slug( $termlink, $original_slug, $translated_slug ) {
		if ( preg_match( '#/?' . preg_quote( $original_slug, '#' ) . '/#', $termlink ) ) {
			$termlink = preg_replace(
				'#^(/?)(' . preg_quote( $original_slug, '#' ) . ')/#',
				'$1' . str_replace( [ '\\', '$' ], [ '\\\\', '\\$' ], $translated_slug ) . '/',
				$termlink
			);
		}

		return $termlink;
	}
}
