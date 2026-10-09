<?php

namespace WPML\StringTranslation\Application\StringCore\Repository;

interface ComponentRepositoryInterface {

	public function getComponentIdAndType( string $text, string $domain, ?string $context = null ): array;
}
