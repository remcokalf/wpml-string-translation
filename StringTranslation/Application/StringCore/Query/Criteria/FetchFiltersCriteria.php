<?php

namespace WPML\StringTranslation\Application\StringCore\Query\Criteria;

class FetchFiltersCriteria {

	private $kind;

	private $type;

	private $source;

	private $domain;

	private $title;

	private $translationPriority;

	private $sourceLanguageCode;

	private $translationStatuses = [];

	public function __construct(
		?int $kind = null,
		?int $type = null,
		?int $source = null,
		?string $domain = null,
		?string $title = null,
		?string $translationPriority = null,
		?string $sourceLanguageCode = null,
		array $translationStatuses = []
	) {
		$this->kind                = $kind;
		$this->type                = $type;
		$this->source              = $source;
		$this->domain              = $domain;
		$this->title               = $title;
		$this->translationPriority = $translationPriority;
		$this->sourceLanguageCode  = $sourceLanguageCode;
		$this->translationStatuses = $translationStatuses;
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
		return null;
	}

	public function getTranslationStatuses(): array {
		return $this->translationStatuses;
	}
}
