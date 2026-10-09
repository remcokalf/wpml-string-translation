<?php

namespace WPML\StringTranslation\Application\StringHtml\Repository;

interface JsonStringsRepositoryInterface {

	public function getAllStringsFromOutput( string $output ): array;
}