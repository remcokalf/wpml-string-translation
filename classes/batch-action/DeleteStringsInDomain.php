<?php

namespace WPML\ST\BatchAction;

use WPML\Ajax\IHandler;
use WPML\Collect\Support\Collection;
use function WPML\Container\make;
use WPML\FP\Either;
use WPML\FP\Fns;
use WPML\FP\Obj;
use WPML\ST\StringsRepository;

class DeleteStringsInDomain implements IHandler {

	private $stringsRepository;

	public function __construct(
		StringsRepository $stringsRepository
	) {
		$this->stringsRepository = $stringsRepository;
	}

	public function run( Collection $data ) {
		if ( ! current_user_can( 'wpml_manage_string_translation' ) && ! current_user_can( 'manage_translations' ) ) {
			return Either::left( 'not allowed' );
		}

		$domain    = $data->get( 'domain', false );
		$batchSize = $data->get( 'batchSize', 1 ); 

		if ( $domain === false ) {
			return Either::left( __( 'Error: please try again', 'wpml-string-translation' ) );
		}

		$strings = $this->stringsRepository->getFromDomains( [ $domain ], $batchSize );
		if ( count( $strings ) > 0 ) {
			wpml_unregister_string_multi($strings);
		}

		return Either::of(
			[
				'completedCount' => count( $strings ) - $this->stringsRepository->countExistingIds( $strings ),
				'navigateToUrl'  => admin_url( 'admin.php?page=' . WPML_ST_MENU_URL ),
			]
		);
	}
}
