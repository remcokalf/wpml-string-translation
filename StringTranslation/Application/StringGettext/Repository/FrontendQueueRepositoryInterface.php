<?php

namespace WPML\StringTranslation\Application\StringGettext\Repository;

use WPML\StringTranslation\Infrastructure\StringGettext\Repository\Dto\GettextStringsByUrl;

interface FrontendQueueRepositoryInterface {
	public function save( array $data );
	public function get(): array;

	public function count(): int;

	public function removeProcessed( int $processed_count );

	public function remove();
}