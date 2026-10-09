<?php

namespace WPML\StringTranslation\Infrastructure\StringGettext\Repository;

use WPML\StringTranslation\Application\Setting\Repository\FilesystemRepositoryInterface;
use WPML\StringTranslation\Application\StringGettext\Repository\FrontendQueueRepositoryInterface;
use WPML\StringTranslation\Infrastructure\Core\Command\SaveFileCommand;
use WP_Filesystem_Base;

class FrontendQueueJsonRepository implements FrontendQueueRepositoryInterface {

	private $filesystemRepository;

	protected $saveFileCommand;

	private $filesystem;

	public function __construct(
		FilesystemRepositoryInterface $filesystemRepository,
		SaveFileCommand $saveFileCommand,
		WP_Filesystem_Base $filesystem
	) {
		$this->filesystemRepository = $filesystemRepository;
		$this->saveFileCommand      = $saveFileCommand;
		$this->filesystem           = $filesystem;
	}

	private function getQueueFilepath(): string {
		$this->filesystemRepository->createQueueDir();
		return $this->filesystemRepository->getQueueDir() . 'gettextfrontend.json';
	}

	public function save( array $data ) {
		$filepath = $this->getQueueFilepath();
		$this->saveFileCommand->run( $filepath, json_encode( $data ) );
	}

	public function get(): array {
		$filepath = $this->getQueueFilepath();

		$data = [];
		if ( $this->filesystem->is_file( $filepath ) && $this->filesystem->is_readable( $filepath ) ) {
			$contents = $this->filesystem->get_contents( $filepath );
			if ( is_string( $contents ) ) {
				$data = json_decode( $contents, true );
				$hasErrors = json_last_error() !== JSON_ERROR_NONE || ! is_array( $data );
				if ( $hasErrors ) {
					$data = [];
				}
			}
		}

		return $data;
	}

	public function count(): int {
		return count( $this->get() );
	}

	public function removeProcessed( int $processed_count ) {
		if ( $processed_count <= 0 ) {
			return;
		}

		$remaining = array_slice( $this->get(), $processed_count );

		if ( count( $remaining ) === 0 ) {
			$this->remove();
			return;
		}

		$this->save( array_values( $remaining ) );
	}

	public function remove() {
		$queueFilepath = $this->getQueueFilepath();
		if ( $this->filesystem->is_file( $queueFilepath ) ) {
			$this->filesystem->delete( $queueFilepath );
		}
	}
}