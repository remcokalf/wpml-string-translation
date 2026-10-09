<?php

namespace WPML\StringTranslation\Application\StringPackage\Query;

use WPML\StringTranslation\Application\StringPackage\Query\Criteria\StringPackageCriteria;
use WPML\StringTranslation\Application\StringPackage\Query\Dto\StringPackageWithTranslationStatusDto;

interface FindStringPackagesQueryInterface {

	public function execute( StringPackageCriteria $criteria );
}