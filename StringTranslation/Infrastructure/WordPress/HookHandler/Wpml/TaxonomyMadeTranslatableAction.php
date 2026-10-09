<?php

namespace WPML\StringTranslation\Infrastructure\WordPress\HookHandler\Wpml;

use WPML\Setup\Option;
use WPML\StringTranslation\Infrastructure\TranslateEverything\UntranslatedTaxonomyLabelStringsFactory;
use WPML\StringTranslation\Infrastructure\WordPress\HookHandler\AbstractActionHookHandler;

class TaxonomyMadeTranslatableAction extends AbstractActionHookHandler {

	const ACTION_NAME = 'wpml_taxonomy_made_translatable';
	const ACTION_ARGS = 1;

	protected function onAction( ...$args ) {
		if ( ! Option::shouldTranslateEverything() ) {
			return;
		}

		( new UntranslatedTaxonomyLabelStringsFactory() )->create()->markEverythingAsUncompleted();
	}
}
