<?php

namespace WPML\ST\Batch\Translation;

use WPML\Collect\Support\Traits\Macroable;
use WPML\FP\Fns;
use WPML\FP\Relation;
use function WPML\FP\curryN;
use function WPML\FP\invoke;
use function WPML\FP\pipe;

class Convert {

	use Macroable;

	public static function init() {

		self::macro(
			'toBatchElements',
			curryN(
				4,
				function ( $getBatchId, $setBatchRecord, $elements, $basketName ) {

					$isString = pipe( invoke( 'get_element_type' ), Relation::equals( 'string' ) );

					list( $stringElements, $otherElements ) = wpml_collect( $elements )->partition( $isString );

					$makeBatchPerLanguage = function ( \WPML_TM_Translation_Batch_Element $element ) use ( $getBatchId, $setBatchRecord, $basketName ) {
						$makeBatchElement = function ( $action, $lang ) use ( $element, $getBatchId, $setBatchRecord, $basketName ) {
							$batchId = $getBatchId( $basketName . '-' . $lang );

							$setBatchRecord( $batchId, $element->get_element_id(), $element->get_source_lang() );

							return Fns::makeN(
								5,
								'WPML_TM_Translation_Batch_Element',
								$batchId,
								'st-batch',
								$element->get_source_lang(),
								[ $lang => $action ],
								[]
							);
						};

						return Fns::map( $makeBatchElement, $element->get_target_langs() );
					};

					$stringElements = $stringElements->map( $makeBatchPerLanguage )
												 ->flatten()
												 ->unique( invoke( 'get_target_langs' ) );

					return $otherElements->merge( $stringElements )
									 ->toArray();
				}
			)
		);

	}
}

Convert::init();

