<?php

namespace WPML\StringTranslation\Application\StringGettext\Repository;

interface StringQuarantineRepositoryInterface {

	public function quarantine( string $domain, array $records ): bool;

	public function getRecords( string $domain ): array;
}
