<?php

namespace WPML\StringTranslation\Infrastructure\WordPress\HookHandler\Wpml;

use WPML\Setup\Option;
use WPML\StringTranslation\Infrastructure\TranslateEverything\UntranslatedTaxonomyLabelStringsFactory;
use WPML\StringTranslation\Infrastructure\WordPress\HookHandler\AbstractActionHookHandler;
use WPML_ST_Tax_Slug_Translation_Settings;

class TaxonomySlugTranslationEnabledAction extends AbstractActionHookHandler {

	public function load() {
		$option = WPML_ST_Tax_Slug_Translation_Settings::OPTION_NAME;
		add_action( 'add_option_' . $option, array( $this, 'onAdded' ), 10, 2 );
		add_action( 'update_option_' . $option, array( $this, 'onUpdated' ), 10, 2 );
	}

	public function onAdded( $option, $value ) {
		$this->requeueIfNewlyEnabled( array(), $value );
	}

	public function onUpdated( $old, $new ) {
		$this->requeueIfNewlyEnabled( $old, $new );
	}

	protected function onAction( ...$args ) {
	}

	private function requeueIfNewlyEnabled( $old, $new ) {
		if ( ! Option::shouldTranslateEverything() ) {
			return;
		}

		$oldTypes = is_array( $old ) && isset( $old['types'] ) ? (array) $old['types'] : array();
		$newTypes = is_array( $new ) && isset( $new['types'] ) ? (array) $new['types'] : array();

		foreach ( $newTypes as $taxonomy => $enabled ) {
			if ( $enabled && empty( $oldTypes[ $taxonomy ] ) ) {
				( new UntranslatedTaxonomyLabelStringsFactory() )->create()->markEverythingAsUncompleted();

				return;
			}
		}
	}
}
