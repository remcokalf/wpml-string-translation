<?php

namespace WPML\StringTranslation\Application\StringCore\Query\Dto;

use WPML\StringTranslation\Application\Translation\Query\Dto\TranslationStatusDto;

class StringWithTranslationStatusDto extends StringDto {

	private $translationStatuses;

	public function __construct(
		int $id,
		string $language,
		string $domain,
		string $context,
		string $name,
		string $value,
		int $status,
		string $translationPriority,
		int $wordCount,
		int $kind,
		int $type,
		array $sources = [],
		array $translationStatuses = []
	) {
		parent::__construct(
			$id,
			$language,
			$domain,
			$context,
			$name,
			$value,
			$status,
			$translationPriority,
			$wordCount,
			$kind,
			$type,
			$sources
		);

		$this->translationStatuses = $translationStatuses;
	}

	public function getTranslationStatuses(): array {
		return $this->translationStatuses;
	}

}