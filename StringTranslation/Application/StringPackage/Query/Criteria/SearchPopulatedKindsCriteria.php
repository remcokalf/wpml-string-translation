<?php

namespace WPML\StringTranslation\Application\StringPackage\Query\Criteria;

class SearchPopulatedKindsCriteria {


	private $itemSectionIds = [];

	private $publicationStatus;

	private $sourceLanguageCode;

	private $targetLanguageCode;

	private $translationStatuses = [];


	public function __construct(
		array $itemSectionIds = [],
		?string $publicationStatus = null,
		string $sourceLanguageCode = '',
		?string $targetLanguageCode = null,
		array $translationStatuses = []
	) {
		$this->sourceLanguageCode  = $sourceLanguageCode;
		$this->itemSectionIds     = $itemSectionIds;
		$this->publicationStatus   = $publicationStatus;
		$this->targetLanguageCode  = $targetLanguageCode;
		$this->translationStatuses = $translationStatuses;
	}


	public function getSourceLanguageCode() {
		return $this->sourceLanguageCode;
	}


	public function getTargetLanguageCode() {
		return $this->targetLanguageCode;
	}


	public function getTranslationStatuses() {
		return $this->translationStatuses;
	}


	public function getItemSectionIds() {
		return $this->itemSectionIds;
	}


	public function getStringPackageTypeIds(): array {
		return array_map(
			function ( $itemSectionId ) {
				return str_replace( 'stringPackage/', '', $itemSectionId );
			},
			array_filter( $this->itemSectionIds,
				function ( $itemSectionId ) {
					return strpos( $itemSectionId, 'stringPackage/' ) === 0;
				}
			)
		);
	}


}
