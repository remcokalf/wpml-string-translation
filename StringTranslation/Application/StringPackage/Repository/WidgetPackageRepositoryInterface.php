<?php

namespace WPML\StringTranslation\Application\StringPackage\Repository;

use WPML\StringTranslation\Application\StringPackage\Query\Dto\StringPackageWithTranslationStatusDto;

interface WidgetPackageRepositoryInterface {

	public function isWidgetPackage( StringPackageWithTranslationStatusDto $stringPackage ): bool;

	public function getUpdatedTitle( string $title ): string;

}
