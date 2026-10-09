<?php

namespace WPML\StringTranslation\Infrastructure\WordPress\HookHandler\TranslateEverything;

use WPML\StringTranslation\Infrastructure\TranslateEverything\UntranslatedTaxonomyLabelStrings;
use WPML\StringTranslation\Infrastructure\TranslateEverything\UntranslatedTaxonomyLabelStringsFactory;
use WPML\StringTranslation\Infrastructure\WordPress\HookHandler\AbstractFilterHookHandler;

class AddUntranslatedTaxonomyLabelStringsStrategyFilter extends AbstractFilterHookHandler {

	const FILTER_NAME     = 'wpml_translate_everything_untranslated_elements_strategies';
	const FILTER_ARGS     = 1;
	const FILTER_PRIORITY = 10;

	private $strategy;

	protected function onFilter( ...$args ) {
		$strategies = $args[0];

		return array_merge( $strategies, [ $this->getStrategy() ] );
	}

	private function getStrategy(): UntranslatedTaxonomyLabelStrings {
		if ( ! $this->strategy ) {
			$this->strategy = ( new UntranslatedTaxonomyLabelStringsFactory() )->create();
		}

		return $this->strategy;
	}
}
