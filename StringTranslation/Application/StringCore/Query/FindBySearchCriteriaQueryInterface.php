<?php

namespace WPML\StringTranslation\Application\StringCore\Query;

use WPML\StringTranslation\Application\StringCore\Query\Criteria\SearchCriteria;
use WPML\StringTranslation\Application\StringCore\Query\Dto\StringWithTranslationStatusDto;

interface FindBySearchCriteriaQueryInterface {

	public function execute( SearchCriteria $criteria );
}