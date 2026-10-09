<?php

namespace WPML\StringTranslation\Application\StringPackage\Query;


use WPML\StringTranslation\Application\StringPackage\Query\Criteria\SearchPopulatedKindsCriteria;

interface SearchPopulatedKindsQueryInterface {


	public function get( SearchPopulatedKindsCriteria $criteria );

}
