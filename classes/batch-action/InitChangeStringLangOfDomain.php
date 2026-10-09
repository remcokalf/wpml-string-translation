<?php

namespace WPML\ST\BatchAction;

use WPML\Ajax\IHandler;
use WPML\Collect\Support\Collection;
use function WPML\Container\make;
use WPML\FP\Either;
use WPML\ST\StringsRepository;

class InitChangeStringLangOfDomain implements IHandler {

	private $sitepress;

	private $stringsRepository;

	private $changeLangDialog;

	public function __construct(
		\SitePress                                 $sitepress,
		StringsRepository                          $stringsRepository,
		\WPML_Change_String_Domain_Language_Dialog $changeLangDialog
	) {
		$this->sitepress         = $sitepress;
		$this->stringsRepository = $stringsRepository;
		$this->changeLangDialog  = $changeLangDialog;
	}

	public function run( Collection $data ) {
		if ( ! current_user_can( 'wpml_manage_string_translation' ) && ! current_user_can( 'manage_translations' ) ) {
			return Either::left( 'not allowed' );
		}

		$domain         = $data->get( 'domain', false );
		$targetLanguage = $data->get( 'targetLanguage', $this->sitepress->get_default_language() );

		if ( $domain === false ) {
			return Either::left( __( 'Error: please try again', 'wpml-string-translation' ) );
		}

		$targetLanguage = \WPML\Language\RequestedLanguage::validate( $targetLanguage, \WPML\Language\RequestedLanguage::SCOPE_CONFIGURED );
		if ( null === $targetLanguage ) {
			return Either::left( 'invalid language' );
		}

		$langs = $this->stringsRepository->getLanguagesUsedInDomains( [ $domain ], [ $targetLanguage ] );
		$count = $this->stringsRepository->getCountInDomainsByLangs( [ $domain ], $langs );

		$this->changeLangDialog->changeLanguageOfStringsInPackages( $domain, $langs, $targetLanguage );
		$this->changeLangDialog->setLanguageOfDomain( $domain, $targetLanguage );

		return Either::of( [
			'totalItemsCount' => $count,
		] );
	}
}

