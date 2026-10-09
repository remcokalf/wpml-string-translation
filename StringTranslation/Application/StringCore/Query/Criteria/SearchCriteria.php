<?php

namespace WPML\StringTranslation\Application\StringCore\Query\Criteria;

class SearchCriteria {

	private $kind;

	private $type;

	private $source;

	private $domain;

	private $title;

	private $translationPriority;

	private $sourceLanguageCode;

	private $targetLanguageCode;

	private $translationStatuses = [];

	private $limit = 10;

	private $offset = 0;

	private $sorting;

	private $ids = [];

	public function __construct(
		?int $kind = null,
		?int $type = null,
		?int $source = null,
		?string $domain = null,
		?string $title = null,
		?string $translationPriority = null,
		?string $sourceLanguageCode = null,
		?string $targetLanguageCode = null,
		array $translationStatuses = [],
		int $limit = 10,
		int $offset = 0,
		?array $sorting = null
	) {
		$this->kind                = $kind;
		$this->type                = $type;
		$this->source              = $source;
		$this->domain              = $domain;
		$this->title               = $title;
		$this->translationPriority = $translationPriority;
		$this->sourceLanguageCode  = $sourceLanguageCode;
		$this->targetLanguageCode  = $targetLanguageCode;
		$this->translationStatuses = $translationStatuses;
		$this->limit               = $limit;
		$this->offset              = $offset;
		$this->sorting             = $sorting;
	}

	public function getKind() {
		return $this->kind;
	}

	public function getType() {
		return $this->type;
	}

	public function getSource() {
		return $this->source;
	}

	public function getDomain() {
		return $this->domain;
	}

	public function getTitle() {
		return $this->title;
	}

	public function getTranslationPriority() {
		return $this->translationPriority;
	}

	public function getSourceLanguageCode() {
		return $this->sourceLanguageCode;
	}

	public function getTargetLanguageCode() {
		return $this->targetLanguageCode;
	}

	public function getTranslationStatuses(): array {
		return $this->translationStatuses;
	}

	public function getLimit(): int {
		return $this->limit;
	}

	public function getOffset(): int {
		return $this->offset;
	}

	public function getSorting() {
		return $this->sorting;
	}

	public function addIds( array $strings ) {
		$this->ids = array_map(
			function( $string ) {
				return is_array( $string ) ? $string['string_id'] : $string;
			},
			$strings
		);
	}

	public function getIds(): array {
		return $this->ids;
	}
}
