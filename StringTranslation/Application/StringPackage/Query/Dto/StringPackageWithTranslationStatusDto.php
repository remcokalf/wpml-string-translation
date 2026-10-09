<?php

namespace WPML\StringTranslation\Application\StringPackage\Query\Dto;

use WPML\StringTranslation\Application\Translation\Query\Dto\TranslationStatusDto;

class StringPackageWithTranslationStatusDto {

	private $id;

	private $title;

	private $name;

	private $lastEdit;

	private $wordCount;

	private $type;

	private $translationStatuses;

	private $translatorNote;

	public function __construct(
		int    $id,
		string $title,
		string $name,
		int    $lastEdit,
		string $type,
		array  $translationStatuses,
		int    $wordCount,
		$translatorNote
	) {
		$this->id                  = $id;
		$this->type                = $type;
		$this->translationStatuses = $translationStatuses;
		$this->title               = $title;
		$this->name                = $name;
		$this->lastEdit            = $lastEdit;
		$this->wordCount           = $wordCount;
		$this->translatorNote      = $translatorNote;
	}

	public function getId(): int {
		return $this->id;
	}

	public function getTitle(): string {
		return $this->title;
	}

	public function getName(): string {
		return $this->name;
	}

	public function getLastEdit(): int {
		return $this->lastEdit;
	}

	public function getType(): string {
		return $this->type;
	}

	public function getTranslationStatuses(): array {
		return $this->translationStatuses;
	}

	public function getWordCount(): int {
		return $this->wordCount;
	}

	public function getTranslatorNote() {
		return $this->translatorNote;
	}
}
