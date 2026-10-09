<?php

namespace WPML\ST\MO\Generate\Process;


use WPML\Utils\Pager;
use WPML\ST\MO\Generate\MultiSite\Executor;

class MultiSiteProcess implements Process {
	private $multiSiteExecutor;

	private $singleSiteProcess;

	private $status;

	private $pager;

	private $subSiteValidator;

	public function __construct(
		Executor $multiSiteExecutor,
		SingleSiteProcess $singleSiteProcess,
		Status $status,
		Pager $pager,
		SubSiteValidator $subSiteValidator
	) {
		$this->multiSiteExecutor = $multiSiteExecutor;
		$this->singleSiteProcess = $singleSiteProcess;
		$this->status            = $status;
		$this->pager             = $pager;
		$this->subSiteValidator  = $subSiteValidator;
	}


	public function runAll() {
		$this->multiSiteExecutor->withEach( $this->runIfSetupComplete( [ $this->singleSiteProcess, 'runAll' ] ) );
		$this->status->markComplete( true );
	}

	public function runPage() {
		$remaining = $this->pager->iterate( $this->multiSiteExecutor->getSiteIds(), function ( $siteId ) {
			return $this->multiSiteExecutor->executeWith(
				$siteId,
				$this->runIfSetupComplete( function () {
					return $this->singleSiteProcess->runPage() === 0;
				} )
			);
		} );

		if ( $remaining === 0 ) {
			$this->multiSiteExecutor->executeWith( Executor::MAIN_SITE_ID, function () {
				$this->status->markComplete( true );
			} );
		}

		return $remaining;
	}

	public function getPagesCount() {
		$isCompletedForAllSites = $this->multiSiteExecutor->executeWith(
			Executor::MAIN_SITE_ID,
			[ $this->status, 'isCompleteForAllSites' ]
		);
		if ( $isCompletedForAllSites ) {
			return 0;
		}

		return $this->multiSiteExecutor->getSiteIds()->count();
	}

	public function isCompleted() {
		return $this->getPagesCount() === 0;
	}


	private function runIfSetupComplete( $callback ) {
		return function () use ( $callback ) {
			if ( $this->subSiteValidator->isValid() ) {
				return $callback();
			}

			return true;
		};
	}
}