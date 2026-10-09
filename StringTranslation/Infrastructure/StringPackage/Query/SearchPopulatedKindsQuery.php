<?php

namespace WPML\StringTranslation\Infrastructure\StringPackage\Query;

use WPML\StringTranslation\Application\StringPackage\Query\Criteria\SearchPopulatedKindsCriteria;
use WPML\StringTranslation\Application\StringPackage\Query\SearchPopulatedKindsQueryInterface;

class SearchPopulatedKindsQuery implements SearchPopulatedKindsQueryInterface {

	private $wpdb;

	private $sitepress;

	private $queryBuilderResolver;

	public function __construct(
		$wpdb,
		$sitepress,
		QueryBuilderResolver $queryBuilderResolver
	) {
		$this->wpdb = $wpdb;
		$this->sitepress = $sitepress;
		$this->queryBuilderResolver = $queryBuilderResolver;
	}

	public function get( SearchPopulatedKindsCriteria $criteria ) {
		$queryBuilder = $this->queryBuilderResolver->resolveSearchPopulatedKindsQueryBuilder();
		$populatedKinds = $criteria->getStringPackageTypeIds();
		foreach( $populatedKinds as $postTypeIndex => $postType ) {
			$query = $queryBuilder->build( $criteria, $postType );
			if ( ! $this->wpdb->get_col( $query ) ) {
				unset( $populatedKinds[ $postTypeIndex ] );
			}
		}

		return $populatedKinds;
	}
}
