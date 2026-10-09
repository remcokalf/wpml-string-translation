<?php

namespace WPML\ST\MO\Generate\Process;


use WPML\ST\MO\File\Manager;
use WPML\ST\MO\Generate\DomainsAndLanguagesRepository;
use WPML\Utils\Pager;

class SingleSiteProcess implements Process {

	CONST TIMEOUT = 5;

	private $domainsAndLanguagesRepository;

	private $manager;

	private $status;

	private $pager;

	private $migrateAdminTexts;

	public function __construct(
		DomainsAndLanguagesRepository $domainsAndLanguagesRepository,
		Manager $manager,
		Status $status,
		Pager $pager,
		callable $migrateAdminTexts
	) {
		$this->domainsAndLanguagesRepository = $domainsAndLanguagesRepository;
		$this->manager                       = $manager;
		$this->status                        = $status;
		$this->pager                         = $pager;
		$this->migrateAdminTexts             = $migrateAdminTexts;
	}


	public function runAll() {
		call_user_func( $this->migrateAdminTexts );
		$this->getDomainsAndLanguages()->each( function ( $row ) {
			$this->manager->add( $row->domain, $row->locale );
		} );

		$this->status->markComplete();
	}

	public function runPage() {
		if ( $this->pager->getProcessedCount() === 0 ) {
			call_user_func( $this->migrateAdminTexts );
		}

		$domains   = $this->getDomainsAndLanguages();;
		$remaining = $this->pager->iterate( $domains, function ( $row ) {
			$this->manager->add( $row->domain, $row->locale );

			return true;
		}, self::TIMEOUT );

		if ( $remaining === 0 ) {
			$this->status->markComplete();
		}

		return $remaining;
	}

	public function getPagesCount() {
		if ( $this->status->isComplete() ) {
			return 0;
		}

		$domains = $this->getDomainsAndLanguages();

		if ( $domains->count() === 0 ) {
			$this->status->markComplete();
		}

		return $domains->count();
	}

	private function getDomainsAndLanguages() {
		return DomainsAndLanguagesRepository::hasTranslationFilesTable()
			? $this->domainsAndLanguagesRepository->get()
			: wpml_collect();
	}

	public function isCompleted() {
		return $this->getPagesCount() === 0;
	}
}
