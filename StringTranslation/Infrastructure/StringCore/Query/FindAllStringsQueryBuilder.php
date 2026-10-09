<?php

namespace WPML\StringTranslation\Infrastructure\StringCore\Query;

use WPML\StringTranslation\Application\StringCore\Query\Criteria\SearchCriteria;
use WPML\StringTranslation\Application\StringCore\Query\Criteria\SearchSelectCriteria;
use WPML\StringTranslation\Application\Setting\Repository\SettingsRepositoryInterface as SettingsRepository;

class FindAllStringsQueryBuilder extends QueryBuilder {

	protected $settingsRepository;

	public function __construct(
		SettingsRepository $settingsRepository
	) {
		$this->settingsRepository = $settingsRepository;
	}

	public function build( SearchCriteria $criteria, SearchSelectCriteria $selectCriteria ) {
		$sql = "
            SELECT {$this->getSelectColumns( $selectCriteria )}
            FROM {$this->getPrefix()}icl_strings strings
            
            {$this->getStringTranslationsSql( $criteria )}
            {$this->buildWhereSql( $criteria )}
            ORDER BY strings.id DESC
            {$this->buildPagination( $criteria )}
        ";

		return $sql;
	}
}
