<?php

namespace WPML\StringTranslation\Infrastructure\StringCore\Query;

use WPML\StringTranslation\Application\StringCore\Query\Criteria\FetchFiltersCriteria;
use WPML\StringTranslation\Application\StringCore\Query\Criteria\SearchCriteria;
use WPML\StringTranslation\Application\Setting\Repository\SettingsRepositoryInterface as SettingsRepository;

class FindCountBySearchCriteriaQueryBuilder extends QueryBuilder {

	protected $settingsRepository;

	public function __construct(
		SettingsRepository $settingsRepository
	) {
		$this->settingsRepository = $settingsRepository;
	}

	protected function buildWhereSql( $criteria ): string {
		$sqlParts = $this->getWhereSqlParts( $criteria );

		$statuses = $criteria->getTranslationStatuses();
		if ( count( $statuses ) > 0 ) {
			$sqlParts[] = "strings.status IN (" . wpml_prepare_in( $statuses ) . ")";
		}

		if ( count( $sqlParts ) === 0 ) {
			return '';
		}

		return ' WHERE ' . implode( ' AND ', $sqlParts );
	}

	public function build( SearchCriteria $criteria ) {
		$sql = "
            SELECT COUNT(DISTINCT strings.id) AS count
            FROM {$this->getPrefix()}icl_strings strings
            {$this->getStringTranslationsSql( $criteria )}
            {$this->getStringPositionsSql( $criteria )}
            {$this->buildWhereSql( $criteria )}
        ";

		return $sql;
	}
}
