<?php

namespace WPML\StringTranslation\Application\StringHtml\Repository;

interface HtmlStringsRepositoryInterface {
	public function getAllStringsFromHtml( string $html ): array;
}