<?php

namespace WPML\StringTranslation\Infrastructure\Core\HookHandler;


use WPML\StringTranslation\Application\StringPackage\Query\Criteria\SearchPopulatedKindsCriteria;
use WPML\StringTranslation\Application\StringPackage\Query\SearchPopulatedKindsQueryInterface;
use WPML\StringTranslation\Infrastructure\WordPress\HookHandler\AbstractFilterHookHandler;

class WPMLPopulatedItemSectionsFilter  extends AbstractFilterHookHandler {
	const FILTER_NAME = 'wpml_tm_populated_item_sections';
	const FILTER_ARGS = 5;
	const FILTER_PRIORITY = 9;

	private $searchPopulatedKindsQuery;

	public function __construct( SearchPopulatedKindsQueryInterface $searchPopulatedKindsQuery ) {
		$this->searchPopulatedKindsQuery = $searchPopulatedKindsQuery;
	}
	protected function onFilter(
		$itemSectionIds = [],
		$publicationStatus = null,
		$sourceLanguageCode = '',
		$targetLanguageCode = null,
		$translationStatuses = [],
		...$args
	) {
		$searchCriteria = new SearchPopulatedKindsCriteria(
			$itemSectionIds,
			$publicationStatus,
			$sourceLanguageCode,
			$targetLanguageCode,
			$translationStatuses
		);

		$populatedStringPackageKinds = $this->searchPopulatedKindsQuery->get( $searchCriteria );

		$itemSectionIds = array_filter(
			$itemSectionIds,
			function( $itemSectionId ) use ( $populatedStringPackageKinds ) {
				return strpos( $itemSectionId, 'stringPackage/' ) !== 0 || in_array( str_replace( 'stringPackage/', '', $itemSectionId), $populatedStringPackageKinds );
			}
		);

		return array_values( $itemSectionIds );
	}

	public function load()
	{
		return parent::load();
	}

}