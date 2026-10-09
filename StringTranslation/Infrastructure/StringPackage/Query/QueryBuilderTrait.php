<?php

namespace WPML\StringTranslation\Infrastructure\StringPackage\Query;

use WPML\Core\SharedKernel\Component\Translation\Domain\TranslationStatus;
use WPML\StringTranslation\Application\StringPackage\Query\Criteria\SearchPopulatedKindsCriteria;
use WPML\StringTranslation\Application\StringPackage\Query\Criteria\StringPackageCriteria;

trait QueryBuilderTrait {
	protected function sqlStringLiteral( $value ): string {
		return "'" . esc_sql( (string) $value ) . "'";
	}

	protected function getLanguageJoinColumName( string $languageCode ) : string {
		return esc_sql( preg_replace( '/[^a-zA-Z0-9]/', '_', $languageCode ) ?: $languageCode );
	}

	private function getSourceLanguageCode( $criteria ): string {
		return $criteria->getSourceLanguageCode()
			? $criteria->getSourceLanguageCode()
			: $this->settingsRepository->getDefaultLanguageCode();
	}

	private function getTargetLanguageCodes( $criteria ): array {
		$languageCodes = $criteria->getTargetLanguageCode() ?
			[  $criteria->getTargetLanguageCode() ] :
			$this->settingsRepository->getActiveLanguageCodes();

		return array_values(
			array_filter(
				$languageCodes,
				function ( $languageCode ) use ( $criteria ) {
					return $languageCode !== $this->getSourceLanguageCode( $criteria );
				}
			)
		);
	}

	protected function buildLanguageInCondition( string $column, array $languageCodes ): string {
		if ( empty( $languageCodes ) ) {
			return '1=0';
		}

		return $column . ' IN (' . wpml_prepare_in( $languageCodes, '%s' ) . ')';
	}

	private function escapeLanguages( array $languageCodes ): array {
		return array_map(
			function ( $languageCode ) {
				return $this->wpdb->prepare( '%s', $languageCode );
			},
			$languageCodes
		);
	}

	private function buildPostTitleCondition(
		StringPackageCriteria $criteria
	): string {
		if ( $criteria->getTitle() ) {
			return $this->wpdb->prepare(
				'AND sp.title LIKE %s',
				'%' . $this->wpdb->esc_like( $criteria->getTitle() ) . '%'
			);
		}

		return '';
	}

	private function buildPagination( StringPackageCriteria $criteria ): string {
		return $this->wpdb->prepare(
			'LIMIT %d OFFSET %d',
			$criteria->getLimit(),
			$criteria->getOffset()
		);
	}

	private function buildSortingQueryPart( StringPackageCriteria $criteria ): string {
		$allowedSortingDirections = [ 'DESC', 'ASC' ];
		$direction                = $allowedSortingDirections[0];

		$queryPart = 'ORDER BY sp.ID';

		if ( $criteria->getSorting() ) {
			$sortingCriteria = $criteria->getSorting();
			$sortingOrder    = strtoupper( $sortingCriteria['order'] );

			if ( $sortingCriteria['by'] === 'title' ) {
				$direction = in_array( $sortingOrder, $allowedSortingDirections ) ?
					$sortingOrder :
					$direction;

				$queryPart = 'ORDER BY sp.title';
			}
		}

		return $queryPart . ' ' . $direction;
	}
}
