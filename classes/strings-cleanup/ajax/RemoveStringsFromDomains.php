<?php

namespace WPML\ST\StringsCleanup\Ajax;

use WPML\Ajax\IHandler;
use WPML\Collect\Support\Collection;
use function WPML\Container\make;
use WPML\FP\Either;
use WPML\FP\Fns;
use WPML\FP\Obj;
use WPML\ST\StringsCleanup\StringsWaitingForTranslation;
use WPML\ST\StringsCleanup\UntranslatedStrings;

class RemoveStringsFromDomains implements IHandler {
	const REMOVE_STRINGS_BATCH_SIZE = 500;

	public function run( Collection $data ) {
		$domains = $data->get( 'domains', false );

		if ( $domains !== false ) {

			$untranslated_strings = make( UntranslatedStrings::class );

			$include_waiting_for_translation = StringsWaitingForTranslation::isRequested( $data );

			return Either::of(
				[
					'total_strings'   => $untranslated_strings->getCountInDomains( $domains, $include_waiting_for_translation ),
					'removed_strings' => $untranslated_strings->remove( Fns::map(
						Obj::prop( 'id' ),
						$untranslated_strings->getFromDomains(
							$domains,
							self::batchSize( $data->get( 'batch_size' ) ),
							$include_waiting_for_translation
						)
					) )
				]
			);
		} else {
			return Either::left( __( 'Error: please try again', 'wpml-string-translation' ) );
		}
	}

	private static function batchSize( $requested ) {
		$size = is_numeric( $requested ) ? (int) $requested : 0;

		return $size > 0 ? min( $size, self::REMOVE_STRINGS_BATCH_SIZE ) : self::REMOVE_STRINGS_BATCH_SIZE;
	}
}
