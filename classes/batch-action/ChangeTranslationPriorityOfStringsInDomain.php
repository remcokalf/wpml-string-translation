<?php

namespace WPML\ST\BatchAction;

use WPML\Ajax\IHandler;
use WPML\Collect\Support\Collection;
use function WPML\Container\make;
use WPML\FP\Either;
use WPML\FP\Fns;
use WPML\FP\Obj;
use WPML\ST\StringsRepository;

class ChangeTranslationPriorityOfStringsInDomain implements IHandler {

	private $sitepress;

	private $stringsRepository;

	private $translationPriority;

	public function __construct(
		\SitePress                         $sitepress,
		StringsRepository                  $stringsRepository,
		\WPML_Strings_Translation_Priority $translationPriority
	) {
		$this->sitepress           = $sitepress;
		$this->stringsRepository   = $stringsRepository;
		$this->translationPriority = $translationPriority;
	}

	public function run( Collection $data ) {
		$domain    = $data->get( 'domain', false );
		$priority  = $data->get( 'priority', false );
		$batchSize = $data->get( 'batchSize', 1 ); 

		if ( $priority === false || $domain === false ) {
			return Either::left( __( 'Error: please try again', 'wpml-string-translation' ) );
		}

		$stringIds = $this->stringsRepository->getStringIdsFromDomainsWithExcludedPriorities( [ $domain ], [ $priority ], $batchSize );
		if ( count( $stringIds ) > 0 ) {
			$this->translationPriority->change_translation_priority_of_strings($stringIds, $priority);
		}

		return Either::of(
			[
				'completedCount' => count( $stringIds ) - $this->stringsRepository->countIdsWithoutPriority( $stringIds, $priority ),
			]
		);
	}
}
