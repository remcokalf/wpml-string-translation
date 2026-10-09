<?php

namespace WPML\StringTranslation\Infrastructure\StringGettext\Command;

use WPML\StringTranslation\Application\StringGettext\Command\InitStorageCommandInterface;
use WPML\StringTranslation\Application\Setting\Repository\FilesystemRepositoryInterface;

class InitStoragePhpCommand implements InitStorageCommandInterface {

	private $filesystemRepository;

	public function __construct(
		FilesystemRepositoryInterface $filesystemRepository
	) {
		$this->filesystemRepository = $filesystemRepository;
	}

	public function run( string $domain ) {
		$this->filesystemRepository->createQueueDir();
	}
}