<?php

namespace WPML\StringTranslation\Application\StringPackage\Query\Criteria;

class StringPackageCriteria {

	private $type;

	private $title;

	private $sourceLanguageCode;

	private $targetLanguageCode;

	private $translationStatuses = [];

	private $limit = 10;

	private $offset = 0;

	private $sorting;

	public function __construct(
		?string $type = null,
		?string $title = null,
		?string $sourceLanguageCode = null,
		?string $targetLanguageCode = null,
		array $translationStatuses = [],
		int $limit = 10,
		int $offset = 0,
		?array $sorting = null
	) {
		$this->type                = $type;
		$this->title               = $title;
		$this->sourceLanguageCode  = $sourceLanguageCode;
		$this->targetLanguageCode  = $targetLanguageCode;
		$this->translationStatuses = $translationStatuses;
		$this->limit               = $limit;
		$this->offset              = $offset;
		$this->sorting             = $sorting;
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
}
