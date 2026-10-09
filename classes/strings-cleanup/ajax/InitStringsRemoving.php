<?php

namespace WPML\ST\StringsCleanup\Ajax;

use WPML\Ajax\IHandler;
use WPML\Collect\Support\Collection;
use function WPML\Container\make;
use WPML\FP\Either;
use WPML\ST\Gettext\AutoRegisterSettings;
use WPML\ST\StringsCleanup\StringsWaitingForTranslation;
use WPML\ST\StringsCleanup\UntranslatedStrings;

class InitStringsRemoving implements IHandler {

	public function run( Collection $data ) {
		$domains = $data->get( 'domains', false );

		if ( $domains !== false ) {

			$untranslatedStrings = make( UntranslatedStrings::class );

			$autoRegsiterSettings = make( AutoRegisterSettings::class );

			if ( $autoRegsiterSettings->isEnabled() ) {
				do_action( 'wpml_st_update_settings', 'setAutoregisterStringsTypeDisabled' );
			}

			return Either::of( [
				'total_strings' => $untranslatedStrings->getCountInDomains(
					$domains,
					StringsWaitingForTranslation::isRequested( $data )
				)
			] );
		} else {
			return Either::left( __( 'Error: please try again', 'wpml-string-translation' ) );
		}
	}
}
