<?php

namespace WPML\StringTranslation\Infrastructure\StringGettext\Repository\Dto;

use WPML\StringTranslation\Application\StringCore\Domain\StringItem;

class GettextStringsByUrl {

	private $strings;

	private $requestUrl;

	public function __construct(
		array  $strings,
		string $requestUrl
	) {
		$this->strings    = $strings;
		$this->requestUrl = $requestUrl;
	}

	public function getStrings(): array {
		return $this->strings;
	}

	public function getRequestUrl(): string {
		return $this->requestUrl;
	}
}