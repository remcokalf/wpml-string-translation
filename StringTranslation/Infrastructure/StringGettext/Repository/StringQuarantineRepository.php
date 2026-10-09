<?php

namespace WPML\StringTranslation\Infrastructure\StringGettext\Repository;

use WPML\StringTranslation\Application\Setting\Repository\FilesystemRepositoryInterface;
use WPML\StringTranslation\Application\StringGettext\Command\CreateFileCommandInterface;
use WPML\StringTranslation\Application\StringGettext\Repository\StringQuarantineRepositoryInterface;
use WPML\StringTranslation\Infrastructure\Setting\Repository\FilesystemRepository;

class StringQuarantineRepository implements StringQuarantineRepositoryInterface {

	const SUBDIR = 'quarantine/strings/';

	const MAX_FILE_BYTES = 2097152;

	private $filesystemRepository;

	private $createFileCommand;

	public function __construct(
		FilesystemRepositoryInterface $filesystemRepository,
		CreateFileCommandInterface $createFileCommand
	) {
		$this->filesystemRepository = $filesystemRepository;
		$this->createFileCommand    = $createFileCommand;
	}

	public function quarantine( string $domain, array $records ): bool {
		if ( array() === $records ) {
			return true;
		}

		$dir = $this->getDir();
		if ( ! is_dir( $dir ) && ! @mkdir( $dir, 0755, true ) && ! is_dir( $dir ) ) {
			return false;
		}

		$filepath = $this->getFilepath( $domain );

		if ( file_exists( $filepath ) && (int) @filesize( $filepath ) > self::MAX_FILE_BYTES ) {
			@rename( $filepath, $filepath . '.old' );
		}

		$merged = [];
		foreach ( array_merge( $this->getRecords( $domain ), $records ) as $record ) {
			if ( ! is_array( $record ) ) {
				continue;
			}
			$identity = md5(
				( isset( $record['name'] ) ? (string) $record['name'] : '' )
				. '|' . ( isset( $record['language'] ) ? (string) $record['language'] : '' )
				. '|' . ( isset( $record['value_b64'] ) ? (string) $record['value_b64'] : '' )
				. '|' . ( isset( $record['reason'] ) ? (string) $record['reason'] : '' )
			);
			if ( ! isset( $merged[ $identity ] ) ) {
				$merged[ $identity ] = $record;
			} else {
				$merged[ $identity ]['attempts'] = ( isset( $merged[ $identity ]['attempts'] ) ? (int) $merged[ $identity ]['attempts'] : 1 ) + 1;
			}
		}

		return $this->createFileCommand->run( array_values( $merged ), $filepath );
	}

	public function getRecords( string $domain ): array {
		$filepath = $this->getFilepath( $domain );
		if ( ! file_exists( $filepath ) || ! is_readable( $filepath ) ) {
			return [];
		}

		try {
			$data = include $filepath;
		} catch ( \Throwable $e ) {
			return [];
		}

		return is_array( $data ) && isset( $data['items'] ) && is_array( $data['items'] )
			? $data['items']
			: [];
	}

	private function getDir(): string {
		return rtrim( $this->filesystemRepository->getQueueDir(), '/\\' ) . '/' . self::SUBDIR;
	}

	private function getFilepath( string $domain ): string {
		return $this->getDir() . FilesystemRepository::encodeDomain( $domain ) . '.php';
	}
}
