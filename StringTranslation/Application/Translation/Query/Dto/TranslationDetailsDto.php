<?php

namespace WPML\StringTranslation\Application\Translation\Query\Dto;

final class TranslationDetailsDto {

	private $languageCode;

	private $translationId;

	private $stringId;

	private $rid;

	private $jobId;

	private $automatic;

	private $editor;

	private $translationService;

	private $reviewStatus;

	private $translatorId;

	private $editorJobId;


	public function __construct(
		string $languageCode,
		int $translationId,
		int $stringId,
		$rid = null,
		$jobId = null,
		$automatic = null,
		$editor = null,
		$translationService = null,
		$reviewStatus = null,
		$translatorId = null,
		$editorJobId = null
	) {
		$this->languageCode       = $languageCode;
		$this->translationId      = $translationId;
		$this->stringId           = $stringId;
		$this->rid                = $rid;
		$this->jobId              = $jobId;
		$this->automatic          = $automatic;
		$this->editor             = $editor;
		$this->translationService = $translationService;
		$this->reviewStatus       = $reviewStatus;
		$this->translatorId       = $translatorId;
		$this->editorJobId        = $editorJobId;
	}

	public function getLanguageCode(): string {
		return $this->languageCode;
	}

	public function getTranslationId(): int {
		return $this->translationId;
	}

	public function getStringId(): int {
		return $this->stringId;
	}

	public function getRid() {
		return $this->rid;
	}

	public function getJobId() {
		return $this->jobId;
	}

	public function getAutomatic() {
		return $this->automatic;
	}

	public function getEditor() {
		return $this->editor;
	}

	public function getTranslationService() {
		return $this->translationService;
	}

	public function getReviewStatus() {
		return $this->reviewStatus;
	}

	public function getTranslatorId() {
		return $this->translatorId;
	}

	public function getEditorJobId() {
		return $this->editorJobId;
	}
}
