<?php

namespace WPML\ST\SlugTranslation\Hooks;

class Hooks {
	private $factory;

	private $slug_translation_settings;

	private $cache;

	private $source;

	private $language;

	public function __construct(
		\WPML_Rewrite_Rule_Filter_Factory $factory,
		\WPML_ST_Slug_Translation_Settings $slug_translation_settings
	) {
		$this->factory                   = $factory;
		$this->slug_translation_settings = $slug_translation_settings;
	}


	public function add_hooks() {
		add_action( 'init', [ $this, 'init' ], \WPML_Slug_Translation_Factory::INIT_PRIORITY );
	}

	public function init() {
		if ( $this->slug_translation_settings->is_enabled() ) {
			add_filter( 'option_rewrite_rules', [ $this, 'filter' ], 1, 1 );
			add_filter( 'flush_rewrite_rules_hard', [ $this, 'flushRewriteRulesHard' ] );
			add_action( 'registered_post_type', [ $this, 'clearCache' ] );
			add_action( 'registered_taxonomy', [ $this, 'clearCache' ] );
		}
	}

	public function filter( $value ) {
		if ( empty( $value ) || apply_filters( 'wpml_st_disable_rewrite_rules', false ) ) {
			return $value;
		}

		$language = $this->getCurrentLanguage();

		if ( null === $this->cache || $this->source !== $value || $this->language !== $language ) {
			$this->cache    = $this->factory->create()->rewrite_rules_filter( $value );
			$this->source   = $value;
			$this->language = $language;
		}

		return $this->cache;
	}

	private function getCurrentLanguage() {
		return (string) apply_filters( 'wpml_current_language', null );
	}

	public function clearCache() {
		$this->cache    = null;
		$this->source   = null;
		$this->language = null;
	}

	public function flushRewriteRulesHard( $hard ) {
		$this->clearCache();

		return $hard;
	}
}
