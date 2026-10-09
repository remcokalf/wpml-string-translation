<?php

namespace WPML\StringTranslation\Infrastructure\Translation;

use WPML\Core\Component\Translation\Application\Service\CompletedTranslationService;
use WPML\StringTranslation\Application\Translation\Query\Dto\TranslationStatusDto;

class TranslationStatusesParser {

	private $getCompletedTranslationService;

	private $completedTranslationService;

	public function __construct( callable $getCompletedTranslationService ) {
		$this->getCompletedTranslationService = $getCompletedTranslationService;
	}

	public function parse( string $translationStatusesRawString, array $ridIndexedJobsArray = [] ): array {
		$translationStatuses = [];
		foreach (
			array_filter( explode( ';', $translationStatusesRawString ) ) as $row
		) {
			$values = [];
			foreach ( explode( ',', $row ) as $pair ) {
				$fields = explode( ':', $pair );
				if ( count( $fields ) === 2 ) {
					$values[ trim( $fields[0] ) ] = trim( $fields[1] );
				}
			}

			$langCode = $values['languageCode'] ?? null;
			if ( ! $langCode ) {
				continue;
			}

			if ( ! isset( $values['reviewStatus'] ) ) {
				$values['reviewStatus'] = 'NULL';
			}
			if ( ! isset( $values['jobId'] ) ) {
				$values['jobId'] = 'NULL';
			}
			if ( ! isset( $values['automatic'] ) ) {
				$values['automatic'] = 'NULL';
			}

			$status = (int) ( $values['status'] ?? ICL_TM_NOT_TRANSLATED );

			$rid = isset( $values['rid'] ) ? (int) $values['rid'] : 0;
			if ( $rid > 0 && isset( $ridIndexedJobsArray[ $rid ] ) ) {
				$job                          = $ridIndexedJobsArray[ $rid ];
				$values['jobId']              = $job['job_id'];
				$values['automatic']          = $job['automatic'];
				$values['translationService'] = $job['translation_service'];
				$values['editor']             = $job['editor'];
				$values['reviewStatus']       = $job['review_status'];
				$values['translated']         = $job['translated'];
				$values['translatorId']       = $job['translator_id'];
				$values['editorJobId']        = $job['editor_job_id'];

			}

			$reviewStatus       = isset( $values['reviewStatus'] ) && $values['reviewStatus'] !== 'NULL' ? $values['reviewStatus'] : null;
			$jobId              = isset( $values['jobId'] ) && $values['jobId'] !== 'NULL' ? (int) $values['jobId'] : null;
			$isTranslated       = isset( $values['translated'] ) && $values['translated'] !== 'NULL' ? (bool) $values['translated'] : false;

			$isTranslated = $isTranslated || $this->isCompleted( $status );
			$automatic          = isset( $values['automatic'] ) && $values['automatic'] !== 'NULL' && (int) $values['automatic'] > 0;
			$translationService = $values['translationService'] ?? 'local';
			$editor             = $values['editor'] ?? null;
			$translatorId       = isset( $values['translatorId'] ) && $values['translatorId'] !== 'NULL' ? (int) $values['translatorId'] : null;
			$editorJobId        = isset( $values['editorJobId'] ) && $values['editorJobId'] !== 'NULL' ? (int) $values['editorJobId'] : null;

			$method = null;
			if ( $status === ICL_TM_DUPLICATE ) {
				$method = 'duplicate';
			} elseif ( $translationService !== 'local' && $translationService !== 'NULL' ) {
				$method = 'translation-service';
			} elseif ( $automatic ) {
				$method = 'automatic';
			} elseif ( $jobId ) {
				$method = 'local-translator';
			}

			$editor = $this->parseEditor( $editor );

			$translationStatuses[ $langCode ] = new TranslationStatusDto(
				$status,
				$reviewStatus,
				$jobId,
				$method,
				$editor,
				$isTranslated,
				$translatorId,
				$editorJobId
			);
		}

		return $translationStatuses;
	}

	private function isCompleted( int $status ): bool {
		if ( ! $this->completedTranslationService ) {
			$this->completedTranslationService = ( $this->getCompletedTranslationService )();
		}

		return $this->completedTranslationService->isTranslationCompleted( $status, false, 0, null );
	}

	private function parseEditor( $editor ) {
		if ( $editor === 'wpml' ) {
			$editor = 'classic';
		} elseif ( $editor === 'wp' ) {
			$editor = 'wordpress';
		} elseif ( ! $editor || $editor === 'NULL' ) {
			$editor = null;
		}

		return $editor;
	}
}
