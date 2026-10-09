<?php

namespace WPML\ST\Gettext;

use SitePress;
use function WPML\Container\make;
use WPML\ST\Gettext\Filters\IFilter;
use WPML\ST\StringsFilter\Provider;

class Hooks implements \IWPML_Action {

	private $filters = [];

	private $sitepress;

	private $string_cache = [];

	private $lang;

	public function __construct( SitePress $sitepress ) {
		$this->sitepress = $sitepress;
	}

	public function add_hooks() {
		add_action( 'plugins_loaded', array( $this, 'init_gettext_hooks' ), 2 );
	}

	public function addFilter( IFilter $filter ) {
		$this->filters[] = $filter;
	}

	public function clearFilters() {
		$this->filters = [];
	}

	public function switch_language_hook( $lang ) {
		$this->lang = $lang;
	}

	public function clear_filters() {
		$filter_provider = make( Provider::class );
		$filter_provider->clearFilters();
	}

	public function init_gettext_hooks() {
		add_filter( 'gettext', [ $this, 'gettext_filter' ], 9, 3 );
		add_filter( 'gettext_with_context', [ $this, 'gettext_with_context_filter' ], 1, 4 );
		add_filter( 'ngettext', [ $this, 'ngettext_filter' ], 9, 5 );
		add_filter( 'ngettext_with_context', [ $this, 'ngettext_with_context_filter' ], 9, 6 );
		add_action( 'wpml_language_has_switched', [ $this, 'switch_language_hook' ] );
	}

	public function gettext_filter( $translation, $text, $domain, $name = false ) {
		if ( is_array( $domain ) ) {
			$domain_key = implode( '', $domain );
		} else {
			$domain_key = $domain;
		}

		if ( ! $name ) {
			$name = md5( $text );
		}

		if ( ! $this->lang ) {
			$this->lang = $this->sitepress->get_current_language();
		}

		$key = $translation . $text . $domain_key . $name . $this->lang;

		if ( isset( $this->string_cache[ $key ] ) ) {
			return $this->string_cache[ $key ];
		}

		foreach ( $this->filters as $filter ) {
			$translation = $filter->filter( $translation, $text, $domain, $name );
		}

		$this->string_cache[ $key ] = $translation;

		return $translation;
	}

	public function gettext_with_context_filter( $translation, $text, $context, $domain ) {
		if ( $context ) {
			$domain = [
				'domain'  => $domain,
				'context' => $context,
			];
		}

		return $this->gettext_filter( $translation, $text, $domain );
	}

	public function ngettext_filter( $translation, $single, $plural, $number, $domain, $context = false ) {
		if ( $number == 1 ) {
			$string_to_translate = $single;
		} else {
			$string_to_translate = $plural;
		}

		return $this->gettext_with_context_filter( $translation, $string_to_translate, $context, $domain );
	}

	public function ngettext_with_context_filter( $translation, $single, $plural, $number, $context, $domain ) {
		return $this->ngettext_filter( $translation, $single, $plural, $number, $domain, $context );
	}
}
