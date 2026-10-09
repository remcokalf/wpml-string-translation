<?php

namespace WPML\StringTranslation\Application\Translation\Query;

use WPML\StringTranslation\Application\Translation\Query\Dto\TranslationDetailsDto;

interface FindTranslationDetailsQueryInterface {

	public function execute( array $stringIds, array $languageCodes ): array;
}