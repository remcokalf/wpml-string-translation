<?php

namespace WPML\StringTranslation\Application\StringHtml\Repository;

use WPML\StringTranslation\Application\StringCore\Domain\StringItem;

interface GettextStringsRepositoryInterface {
	public function filterOnlyGettextStringsThatMatchesHtmlStrings( array $gettextStrings, array $htmlStrings ): array;
}