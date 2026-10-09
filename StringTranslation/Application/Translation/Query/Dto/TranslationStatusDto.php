<?php

namespace WPML\StringTranslation\Application\Translation\Query\Dto;

use WPML\PHP\ConstructableFromArrayInterface;
use WPML\PHP\ConstructableFromArrayTrait;

final class TranslationStatusDto {

	private $status;

	private $reviewStatus;

	private $jobId;

	private $method;

	private $editor;

	private $isTranslated;

	private $translatorId;

	private $ateJobId;


	public function __construct(
	$status,
	$reviewStatus = null,
	$jobId = null,
	$method = null,
	$editor = null,
	$isTranslated = false,
	$translatorId = null,
	$ateJobId = null
	) {
		$allowedReviewStatus = [ 'NEEDS_REVIEW', 'EDITING', 'ACCEPTED' ];
		$allowedMethod       = [ 'duplicate', 'translation-service', 'automatic', 'manual', 'local-translator' ];
		$allowedEditor       = [ 'classic', 'wordpress', 'ate', 'none' ];

		$this->status       = $status;
		$this->reviewStatus = in_array( $reviewStatus, $allowedReviewStatus, true ) ? $reviewStatus : null;
		$this->jobId        = $jobId;
		$this->method       = in_array( $method, $allowedMethod, true ) ? $method : null;
		$this->editor       = in_array( $editor, $allowedEditor, true ) ? $editor : null;
		$this->isTranslated = $isTranslated;
		$this->translatorId = $translatorId;
		$this->ateJobId     = $ateJobId;
	}


	public function getStatus(): int {
		return $this->status;
	}


	public function getReviewStatus() {
		return $this->reviewStatus;
	}


	public function getJobId() {
		return $this->jobId;
	}


	public function getMethod() {
		return $this->method;
	}


	public function getEditor() {
		return $this->editor;
	}

	public function getIsTranslated() {
		return $this->isTranslated;
	}

	public function getTranslatorId() {
		return $this->translatorId;
	}

	public function getAteJobId() {
		return $this->ateJobId;
	}

	public function toArray(): array {
		return [
			'status'       => $this->status,
			'reviewStatus' => $this->reviewStatus,
			'jobId'        => $this->jobId,
			'method'       => $this->method,
			'editor'       => $this->editor,
			'isTranslated' => $this->isTranslated,
			'translatorId' => $this->translatorId,
			'ateJobId'     => $this->ateJobId,
		];
	}


}
