<?php

namespace WPML\StringTranslation\Infrastructure\StringPackage\Query\ManyLanguagesStrategy;

use WPML\StringTranslation\Application\StringPackage\Query\Criteria\SearchPopulatedKindsCriteria;
use WPML\StringTranslation\Application\StringPackage\Query\Criteria\StringPackageCriteria;
use WPML\Core\SharedKernel\Component\Translation\Domain\TranslationStatus;

trait TranslationStatusQueryBuilderTrait {
	protected function buildTranslationStatusCondition( $criteria, array $targetLanguageCodes ): string {
		if ( empty( $targetLanguageCodes ) ) {
			return '1';
		}

		$gluedLanguageCodes = wpml_prepare_in( $targetLanguageCodes, '%s' );

		$isNotTranslated = in_array( TranslationStatus::NOT_TRANSLATED, $criteria->getTranslationStatuses() );
		$needsUpdate     = in_array( TranslationStatus::NEEDS_UPDATE, $criteria->getTranslationStatuses() );
		$complete        = in_array( TranslationStatus::COMPLETE, $criteria->getTranslationStatuses() );

		$statuses = $this->getStatusesToQuery( $criteria );

		$appendConditions     = [];
		$targetLanguagesCount = count( $targetLanguageCodes );

		if ( $isNotTranslated ) {
			$translationStatuses = wpml_prepare_in(
				[
					ICL_TM_NOT_TRANSLATED,
					ICL_TM_NEEDS_UPDATE,
					ICL_TM_NEEDS_REVIEW,
					ICL_TM_WAITING_FOR_TRANSLATOR,
					ICL_TM_IN_PROGRESS,
				],
				'%d'
			);

			$appendConditions[] = "
						        (
						          SELECT COUNT(DISTINCT t.language_code)
						          FROM {$this->wpdb->prefix}icl_translations t
						          INNER JOIN {$this->wpdb->prefix}icl_translation_status ts
						              ON ts.translation_id = t.translation_id
						          WHERE t.trid = source_t.trid
						              AND t.language_code IN ({$gluedLanguageCodes})
						              AND t.element_id IS NULL
						              AND ts.status NOT IN ({$translationStatuses})
						        ) < {$targetLanguagesCount} 
						      ";
		}
		if ( $needsUpdate ) {
			$appendConditions[] = 'target_ts.needs_update = 1';
		}
		if ( $complete ) {
			$appendConditions[] = '(target_t.element_id IS NULL AND ((target_ts.status = 10) OR (target_ts.status = 0 OR target_ts.status IS NULL)))';
		}

		if ( ! empty( $statuses ) ) {
			$appendConditions[] = sprintf(
				'target_ts.status IN %s' .
				( $complete ? ' AND target_ts.needs_update = 0' : '' ),
				'(' . implode( ', ', $statuses ) . ')'
			);
		}

		if ( ! empty( $appendConditions ) ) {
			return '(' . implode( ' OR ', $appendConditions ) . ')';
		}

		return '1';
	}

	private function getStatusesToQuery( $criteria ) {
		$statuses = [];
		foreach ( $criteria->getTranslationStatuses() as $status ) {
			if ( in_array( $status, [ TranslationStatus::NOT_TRANSLATED, TranslationStatus::NEEDS_UPDATE ] ) ) {
				continue;
			}
			$statuses[] = $status;
		}

		return $statuses;
	}
}