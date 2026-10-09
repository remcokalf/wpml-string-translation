<?php

namespace WPML\StringTranslation\Infrastructure\StringGettext\Command;

use WPML\StringTranslation\Application\Setting\Repository\FilesystemRepositoryInterface;
use WPML\StringTranslation\Application\StringGettext\Command\DeletePendingStringsCommandInterface;
use WP_Filesystem_Base;

class DeletePendingStringsPhpCommand implements DeletePendingStringsCommandInterface {

	private $filesystemRepository;

	private $filesystem;

	public function __construct(
		FilesystemRepositoryInterface $filesystemRepository,
		WP_Filesystem_Base $filesystem
	) {
		$this->filesystemRepository = $filesystemRepository;
		$this->filesystem           = $filesystem;
	}

	public function run( string $domain ) {
		$filepath = $this->filesystemRepository->getPendingStringsFilepath( $domain, 'php' );
		if ( $this->filesystem->is_file( $filepath ) ) {
			$this->filesystem->delete( $filepath );
		}
	}
}