<?php

namespace WPML\StringTranslation\Infrastructure\StringGettext\Command;

use WPML\StringTranslation\Application\Setting\Repository\FilesystemRepositoryInterface;
use WPML\StringTranslation\Application\StringGettext\Command\ClearAllStoragesCommandInterface;
use WP_Filesystem_Base;

class ClearAllStoragesCommand implements ClearAllStoragesCommandInterface {

	private $filesystemRepository;

	private $filesystem;

	public function __construct(
		FilesystemRepositoryInterface $filesystemRepository,
		WP_Filesystem_Base $filesystem
	) {
		$this->filesystemRepository = $filesystemRepository;
		$this->filesystem           = $filesystem;
	}

	public function run() {
		$this->clearDirectory( $this->filesystemRepository->getQueueDir() );
	}

	private function clearDirectory( string $directory ) {
		if ( ! $this->filesystem->is_dir( $directory ) ) {
			return;
		}

		$entries = $this->filesystem->dirlist( $directory );

		foreach ( is_array( $entries ) ? $entries : [] as $entry => $info ) {
			$path = rtrim( $directory, '/\\' ) . '/' . $entry;

			if ( is_link( $path ) ) {
				$this->filesystem->delete( $path, false, 'f' );
				continue;
			}

			if ( isset( $info['type'] ) && 'd' === $info['type'] ) {
				$this->clearDirectory( $path );
				$this->filesystem->rmdir( $path );
				continue;
			}

			$this->filesystem->delete( $path, false, 'f' );
		}
	}
}
