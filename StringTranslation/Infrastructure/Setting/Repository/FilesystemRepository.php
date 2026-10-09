<?php

namespace WPML\StringTranslation\Infrastructure\Setting\Repository;

use WPML\StringTranslation\Application\Setting\Repository\FilesystemRepositoryInterface;
use WPML\FP\Str;
use WP_Filesystem_Base;

class FilesystemRepository implements FilesystemRepositoryInterface {

	const ENCODED_DOMAIN_PREFIX = 'wpmlenc-';

	private $filesystem;

	public function __construct( WP_Filesystem_Base $filesystem ) {
		$this->filesystem = $filesystem;
	}

	public function createQueueDir() {
		$this->makeDirRecursive( $this->getQueueDir() );
	}

	private function makeDirRecursive( string $dir ) {
		$dir = rtrim( $dir, '/\\' );

		if ( '' === $dir || $this->filesystem->is_dir( $dir ) ) {
			return;
		}

		$missing = [];
		while ( '' !== $dir && ! $this->filesystem->is_dir( $dir ) ) {
			$missing[] = $dir;
			$parent    = dirname( $dir );
			if ( $parent === $dir ) {
				break;
			}
			$dir = $parent;
		}

		$chmod = defined( 'FS_CHMOD_DIR' ) ? FS_CHMOD_DIR : 0777;
		foreach ( array_reverse( $missing ) as $path ) {
			$this->filesystem->mkdir( $path, $chmod );
		}
	}

	private function getWpmlDir(): string {
		return WP_LANG_DIR . '/wpml/';
	}

	public function getQueueDir(): string {
		$subdir = '';
		if ( is_multisite() ) {
			$subdir = get_current_blog_id() . '/';
		}

		return $this->getWpmlDir() . 'queue/' . $subdir;
	}

	public function getFilepath( string $filename ): string {
		return $this->getQueueDir() . $filename;
	}

	public function getProcessedStringsFilepath( string $domain, string $ext = 'php' ): string {
		return $this->getQueueDir() . self::encodeDomain( $domain ) . '.' . $this->validateExt( $ext );
	}

	public function getPendingStringsFilepath( string $domain, string $ext = 'php' ): string {
		return $this->getQueueDir() . self::encodeDomain( $domain ) . '_pending.' . $this->validateExt( $ext );
	}

	public static function encodeDomain( string $domain ): string {
		if ( strpos( $domain, self::ENCODED_DOMAIN_PREFIX ) !== 0
			&& preg_match( '/^[A-Za-z0-9_-]+(\.[A-Za-z0-9_-]+)*$/', $domain )
		) {
			return $domain;
		}

		return self::ENCODED_DOMAIN_PREFIX . bin2hex( $domain );
	}

	private function decodeDomain( string $filename ): string {
		if ( strpos( $filename, self::ENCODED_DOMAIN_PREFIX ) !== 0 ) {
			return $filename;
		}

		$encoded = substr( $filename, strlen( self::ENCODED_DOMAIN_PREFIX ) );
		if ( '' === $encoded ) {
			return '';
		}

		if ( strlen( $encoded ) % 2 === 0 && ctype_xdigit( $encoded ) ) {
			return hex2bin( $encoded );
		}

		return $filename;
	}

	private function validateExt( string $ext ): string {
		if ( ! preg_match( '/^[A-Za-z0-9]+$/', $ext ) ) {
			throw new \InvalidArgumentException( 'Invalid queue file extension.' );
		}

		return $ext;
	}

	public function getDomainFromFilepath( string $filepath ): string {
		$parts    = explode( '/', $filepath );
		$filename = $parts[ count( $parts ) - 1 ];

		$filenameParts = explode( '.', $filename );
		array_pop( $filenameParts );
		$filename = implode( '.', $filenameParts );

		if ( substr( $filename, -strlen( '_pending' ) ) === '_pending' ) {
			$filename = substr( $filename, 0, -strlen( '_pending' ) );
		}

		return $this->decodeDomain( $filename );
	}

	private function getQueueFileData( $ext = 'php' ): array {
		$filenames = [];
		if ( $this->filesystem->is_dir( $this->getQueueDir() ) ) {
			$entries = $this->filesystem->dirlist( $this->getQueueDir() );
			if ( is_array( $entries ) ) {
				$tmpFileExt = '_tmp.' . $ext;
				foreach ( $entries as $name => $info ) {
					if ( isset( $info['type'] ) && 'f' !== $info['type'] ) {
						continue;
					}
					if ( substr( $name, -strlen( $tmpFileExt ) ) === $tmpFileExt ) {
						continue;
					}
					$filenames[] = $name;
				}
			}
		}
		$fileData  = [
			'processed' => [
				'strings' => [
					'filenames' => [],
					'filePaths' => [],
				],
				'domains' => [],
			],
			'pending' => [
				'strings' => [
					'filenames' => [],
					'filePaths' => [],
				],
				'domains' => [],
				'paths'   => [],
			],
		];

		foreach ( $filenames as $filename ) {
			$pendingSettingsExt = '_settings_pending.' . $ext;
			$pendingStringsExt  = '_pending.' . $ext;
			$stringsExt         = '.' . $ext;

			$filepath = $this->getQueueDir() . $filename;
			$domain   = $this->getDomainFromFilepath( $filename );

			if ( substr( $filename, -strlen( $pendingStringsExt ) ) === $pendingStringsExt ) {
				$fileData['pending']['strings']['filenames'][] = $filename;
				$fileData['pending']['strings']['filePaths'][] = $filepath;
				$fileData['pending']['domains'][]              = $domain;
			} else if ( substr( $filename, -strlen( $stringsExt ) ) === $stringsExt ) {
				$fileData['processed']['strings']['filenames'][] = $filename;
				$fileData['processed']['strings']['filePaths'][] = $filepath;
				$fileData['processed']['domains'][]              = $domain;
			}
		}

		$fileData['processed']['domains'] = array_unique( $fileData['processed']['domains'] );
		$fileData['pending']['domains']   = array_unique( $fileData['pending']['domains'] );

		return $fileData;
	}

	public function getPendingStringDomainNames( string $ext = 'php' ): array {
		return $this->getQueueFileData( $ext )['pending']['domains'];
	}

	public function getProcessedStringDomainNames( string $ext = 'php' ): array {
		return $this->getQueueFileData( $ext )['processed']['domains'];
	}

	public function getProcessedStringFilenames( string $ext = 'php' ): array {
		return $this->getQueueFileData( $ext )['processed']['strings']['filenames'];
	}

	public function getProcessedStringFilePaths( string $ext = 'php' ): array {
		return $this->getQueueFileData( $ext )['processed']['strings']['filePaths'];
	}

	public function getPendingStringFilenames( string $ext = 'php' ): array {
		return $this->getQueueFileData( $ext )['pending']['strings']['filenames'];
	}

	public function getPendingStringFilePaths( string $ext = 'php' ): array {
		return $this->getQueueFileData( $ext )['pending']['strings']['filePaths'];
	}
}