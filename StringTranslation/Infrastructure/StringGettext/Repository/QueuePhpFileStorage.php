<?php

namespace WPML\StringTranslation\Infrastructure\StringGettext\Repository;

use WPML\StringTranslation\Application\StringCore\Domain\StringItem;
use WPML\StringTranslation\Application\Setting\Repository\FilesystemRepositoryInterface;
use WPML\StringTranslation\Application\StringGettext\Repository\QueueStorageInterface;
use WPML\StringTranslation\Application\StringCore\Domain\Factory\StringItemFactory;
use WPML\StringTranslation\Infrastructure\StringGettext\Command\CreatePhpFileCommand;
use WPML\StringTranslation\Infrastructure\StringGettext\Command\PendingStringsFileLock;
use WPML\StringTranslation\Infrastructure\StringGettext\Repository\Util\PendingStringsMerger;

class QueuePhpFileStorage implements QueueStorageInterface {

	const CLAIM_CHUNK_SIZE          = 1000;
	const CLAIM_EXPANDED_WORK_LIMIT = 1000;
	const CURSOR_CLAIM_CHUNK_SIZE   = 10;
	const CURSOR_CLAIM_WORK_LIMIT   = 10000;

	const CLAIM_PROGRESS_VERSION = 2;
	const CLAIM_PROGRESS_SUFFIX  = '.progress.json';

	private $filesystemRepository;

	private $stringItemFactory;

	private $fileLock;

	private $createPhpFile;

	private $merger;

	private $claimLocks = [];

	private $claimFingerprints = [];

	public function __construct(
		FilesystemRepositoryInterface $filesystemRepository,
		StringItemFactory              $stringItemFactory,
		PendingStringsFileLock         $fileLock,
		CreatePhpFileCommand            $createPhpFile,
		PendingStringsMerger           $merger
	) {
		$this->filesystemRepository = $filesystemRepository;
		$this->stringItemFactory    = $stringItemFactory;
		$this->fileLock             = $fileLock;
		$this->createPhpFile        = $createPhpFile;
		$this->merger               = $merger;
	}

	private function get( string $phpFilepath ): array {
		$result = $this->readQueueFile( $phpFilepath );

		return $result['items'];
	}

	private function readQueueFile( string $phpFilepath ): array {
		$invalidResult = [
			'valid' => false,
			'items' => [],
		];

		if ( ! file_exists( $phpFilepath ) ) {
			return $invalidResult;
		}

		$queueDir = realpath( $this->filesystemRepository->getQueueDir() );
		$realpath = realpath( $phpFilepath );
		if ( false === $queueDir || false === $realpath
			|| ! is_file( $realpath )
			|| strpos( $realpath, $queueDir . DIRECTORY_SEPARATOR ) !== 0
		) {
			return $invalidResult;
		}

		if ( ! is_readable( $realpath ) ) {
			$this->quarantineFile( $phpFilepath );
			return $invalidResult;
		}

		try {
			$result = include $realpath;
		} catch ( \Throwable $e ) {
			$this->quarantineFile( $phpFilepath );
			return $invalidResult;
		}
		if ( ! $result || ! is_array( $result ) ) {
			$this->quarantineFile( $phpFilepath );
			return $invalidResult;
		}

		if ( ! isset( $result['items'] ) || ! is_array( $result['items'] ) ) {
			$this->quarantineFile( $phpFilepath );
			return $invalidResult;
		}

		return [
			'valid' => true,
			'items' => $result['items'],
		];
	}

	private function quarantineFile( string $phpFilepath ) : bool {
		if ( ! file_exists( $phpFilepath ) ) {
			return true;
		}
		if ( $this->isClaimFilepath( $phpFilepath ) && ! $this->removeClaimProgressFiles( $phpFilepath ) ) {
			return false;
		}

		$quarantineDir = dirname( $phpFilepath ) . '/quarantine/';
		if ( ! is_dir( $quarantineDir ) ) {
			@mkdir( $quarantineDir, 0777, true );
		}
		if ( ! is_dir( $quarantineDir ) ) {
			return false;
		}

		$token              = str_replace( '.', '', uniqid( '', true ) );
		$quarantineFilepath = $quarantineDir . basename( $phpFilepath ) . '.corrupt.' . $token . '.php';

		return @rename( $phpFilepath, $quarantineFilepath );
	}

	public function getPendingStringDomainNames(): array {
		$domains = $this->filesystemRepository->getPendingStringDomainNames( 'php' );

		foreach ( $this->getClaimFilepaths() as $claimFilepath ) {
			$domain = $this->getDomainFromClaimFilepath( $claimFilepath );
			if ( null !== $domain ) {
				$domains[] = $domain;
			}
		}

		$domains = array_values( array_unique( $domains ) );
		sort( $domains, SORT_STRING );

		return $domains;
	}

	public function getProcessedStringDomainNames(): array {
		return $this->filesystemRepository->getProcessedStringDomainNames( 'php' );
	}

	public function getProcessedStringsByDomain( string $domain ): array {
		return $this->get( $this->filesystemRepository->getProcessedStringsFilepath( $domain, 'php' ) );
	}

	public function getPendingStringsByDomain( string $domain ): array {
		$strings = [];
		foreach ( $this->getClaimFilepaths( $domain ) as $claimFilepath ) {
			$strings = $this->merger->merge( $strings, $this->get( $claimFilepath ) );
		}

		return $this->merger->merge(
			$strings,
			$this->get( $this->filesystemRepository->getPendingStringsFilepath( $domain, 'php' ) )
		);
	}

	public function claimPendingStringsByDomain( string $domain ): array {
		$claimFilepaths = $this->getClaimFilepaths( $domain );
		if ( count( $claimFilepaths ) > 0 ) {
			return $this->acquireClaim( $domain, $claimFilepaths[0] );
		}

		$pendingFilepath = $this->filesystemRepository->getPendingStringsFilepath( $domain, 'php' );
		$writerLock      = $this->fileLock->acquire( $pendingFilepath . '.lock' );
		if ( ! $writerLock ) {
			return [];
		}

		$claimFilepath = null;
		try {
			$claimFilepaths = $this->getClaimFilepaths( $domain );
			if ( count( $claimFilepaths ) > 0 ) {
				$claimFilepath = $claimFilepaths[0];
			} elseif ( file_exists( $pendingFilepath ) ) {
				$processingDir = $this->getProcessingDir( true );
				if ( null !== $processingDir ) {
					$token         = str_replace( '.', '', uniqid( '', true ) );
					$claimFilepath = $processingDir . basename( $pendingFilepath ) . '.' . $token . '.php';
					if ( ! @rename( $pendingFilepath, $claimFilepath ) ) {
						$claimFilepath = null;
					} else {
						$this->invalidateOpcache( $pendingFilepath );
						$this->invalidateOpcache( $claimFilepath );
					}
				}
			}
		} finally {
			$this->fileLock->release( $writerLock );
		}

		return null === $claimFilepath ? [] : $this->acquireClaim( $domain, $claimFilepath );
	}

	public function acknowledgePendingStringsClaim( string $claimId ): bool {
		if ( ! isset( $this->claimLocks[ $claimId ] ) || ! $this->isClaimFilepath( $claimId ) ) {
			return false;
		}

		if ( ! $this->removeClaimProgressFiles( $claimId ) ) {
			return false;
		}

		if ( ! file_exists( $claimId ) || ! @unlink( $claimId ) ) {
			return false;
		}

		unset( $this->claimFingerprints[ $claimId ] );
		$this->invalidateOpcache( $claimId );
		return true;
	}

	public function getPendingStringsClaimProgress( string $claimId ): array {
		$default = $this->getDefaultClaimProgress();
		if ( ! $this->ownsClaim( $claimId ) ) {
			return $default;
		}

		$progressFilepath = $this->getClaimProgressFilepath( $claimId );
		if ( ! file_exists( $progressFilepath ) ) {
			return $default;
		}

		$contents = @file_get_contents( $progressFilepath );
		$progress = false === $contents ? null : json_decode( $contents, true );
		$fingerprint = $this->getClaimFingerprint( $claimId );

		if (
			null === $fingerprint
			|| ! is_array( $progress )
			|| ! isset( $progress['version'], $progress['claimFingerprint'], $progress['completedLocales'], $progress['diagnostics'] )
			|| self::CLAIM_PROGRESS_VERSION !== $progress['version']
			|| ! is_string( $progress['claimFingerprint'] )
			|| ! hash_equals( $fingerprint, $progress['claimFingerprint'] )
			|| ! $this->hasValidClaimProgressPayload( $progress )
		) {
			$this->removeClaimProgressFiles( $claimId );
			return $default;
		}

		$result = [
			'version'          => self::CLAIM_PROGRESS_VERSION,
			'completedLocales' => array_values( $progress['completedLocales'] ),
			'diagnostics'      => $progress['diagnostics'],
		];
		if ( isset( $progress['workCursor'] ) ) {
			$result['workCursor'] = $progress['workCursor'];
		}
		if ( isset( $progress['localeBatch'] ) ) {
			$result['localeBatch'] = $progress['localeBatch'];
		}

		return $result;
	}

	public function savePendingStringsClaimProgress( string $claimId, array $progress ): bool {
		if ( ! $this->ownsClaim( $claimId ) || ! $this->hasValidClaimProgressPayload( $progress ) ) {
			return false;
		}

		if ( isset( $progress['version'] ) && self::CLAIM_PROGRESS_VERSION !== $progress['version'] ) {
			return false;
		}

		$fingerprint = $this->getClaimFingerprint( $claimId );
		if ( null === $fingerprint ) {
			return false;
		}

		$payload = [
			'version'          => self::CLAIM_PROGRESS_VERSION,
			'claimFingerprint' => $fingerprint,
			'completedLocales' => array_values( array_unique( $progress['completedLocales'] ) ),
			'diagnostics'      => isset( $progress['diagnostics'] ) ? $progress['diagnostics'] : [],
		];
		if ( isset( $progress['workCursor'] ) ) {
			$payload['workCursor'] = $progress['workCursor'];
		}
		if ( isset( $progress['localeBatch'] ) ) {
			$payload['localeBatch'] = $progress['localeBatch'];
		}
		$json = json_encode( $payload );
		if ( false === $json ) {
			return false;
		}

		$progressFilepath = $this->getClaimProgressFilepath( $claimId );
		$token            = str_replace( '.', '', uniqid( '', true ) );
		$tempFilepath     = $progressFilepath . '.' . $token . '.tmp';
		$bytesWritten     = @file_put_contents( $tempFilepath, $json, LOCK_EX );
		if ( false === $bytesWritten || $bytesWritten !== strlen( $json ) ) {
			@unlink( $tempFilepath );
			return false;
		}

		if ( ! @rename( $tempFilepath, $progressFilepath ) ) {
			@unlink( $tempFilepath );
			return false;
		}

		return true;
	}

	public function releasePendingStringsClaim( string $claimId ) {
		if ( ! isset( $this->claimLocks[ $claimId ] ) ) {
			return;
		}

		$this->fileLock->release( $this->claimLocks[ $claimId ] );
		unset( $this->claimLocks[ $claimId ] );
		unset( $this->claimFingerprints[ $claimId ] );

		if ( ! file_exists( $claimId ) ) {
			@unlink( $claimId . '.lock' );
		}
	}

	private function acquireClaim( string $domain, string $claimFilepath ): array {
		$claimLock = $this->fileLock->acquire( $claimFilepath . '.lock' );
		if ( ! $claimLock ) {
			return [];
		}

		if ( ! file_exists( $claimFilepath ) ) {
			$this->fileLock->release( $claimLock );
			@unlink( $claimFilepath . '.lock' );
			return [];
		}

		$result = $this->readQueueFile( $claimFilepath );
		if ( ! $result['valid'] || ! $this->hasValidPendingStringSchema( $result['items'] ) ) {
			if ( $result['valid'] ) {
				$this->quarantineFile( $claimFilepath );
			}

			$this->claimLocks[ $claimFilepath ] = $claimLock;
			$this->releasePendingStringsClaim( $claimFilepath );

			return [];
		}

		if ( ! file_exists( $claimFilepath ) ) {
			$this->fileLock->release( $claimLock );
			@unlink( $claimFilepath . '.lock' );
			return [];
		}

		$chunks = $this->buildClaimChunks( $result['items'] );
		if ( count( $chunks ) > 1 ) {
			return $this->splitClaimAndAcquireFirstChunk( $domain, $claimFilepath, $claimLock, $chunks );
		}

		$this->claimLocks[ $claimFilepath ] = $claimLock;

		return [
			'id'      => $claimFilepath,
			'domain'  => $domain,
			'strings' => $result['items'],
		];
	}

	private function splitClaimAndAcquireFirstChunk( string $domain, string $claimFilepath, $claimLock, array $chunks ): array {
		$chunkFilepaths = [];

		foreach ( $chunks as $index => $chunk ) {
			$chunkFilepath   = $this->getChunkFilepath( $claimFilepath, $index );
			$chunkFilepaths[] = $chunkFilepath;

			if ( ! $this->createPhpFile->run( $chunk, $chunkFilepath ) ) {
				$this->removeSplitChunks( $claimFilepath );
				$this->releaseClaimLockResource( $claimFilepath, $claimLock );
				return [];
			}
		}

		if ( ! $this->removeUnexpectedSplitChunks( $claimFilepath, $chunkFilepaths ) ) {
			$this->removeSplitChunks( $claimFilepath );
			$this->releaseClaimLockResource( $claimFilepath, $claimLock );
			return [];
		}

		if ( ! $this->removeClaimProgressFiles( $claimFilepath ) || ! @unlink( $claimFilepath ) ) {
			$this->removeSplitChunks( $claimFilepath );
			$this->releaseClaimLockResource( $claimFilepath, $claimLock );
			return [];
		}

		$this->invalidateOpcache( $claimFilepath );
		$this->releaseClaimLockResource( $claimFilepath, $claimLock );

		return $this->acquireClaim( $domain, $chunkFilepaths[0] );
	}

	private function buildClaimChunks( array $strings ): array {
		$chunks       = [];
		$currentChunk = [];
		$currentWork  = 0;
		$currentMode  = null;

		foreach ( $strings as $key => $stringData ) {
			$itemWork = $this->getExpandedWorkForClaimItem( $stringData );
			$itemMode = $itemWork > self::CURSOR_CLAIM_WORK_LIMIT
				? 'oversized'
				: (
					$itemWork > intdiv( self::CLAIM_EXPANDED_WORK_LIMIT, 2 )
						? 'cursor'
						: 'legacy'
				);

			if ( 'oversized' === $itemMode ) {
				if ( count( $currentChunk ) > 0 ) {
					$chunks[] = $currentChunk;
				}
				$chunks[]     = [ $key => $stringData ];
				$currentChunk = [];
				$currentWork  = 0;
				$currentMode  = null;
				continue;
			}

			$chunkSizeLimit = 'cursor' === $itemMode
				? self::CURSOR_CLAIM_CHUNK_SIZE
				: self::CLAIM_CHUNK_SIZE;
			$chunkWorkLimit = 'cursor' === $itemMode
				? self::CURSOR_CLAIM_WORK_LIMIT
				: self::CLAIM_EXPANDED_WORK_LIMIT;
			if (
				count( $currentChunk ) > 0
				&& (
					$currentMode !== $itemMode
					|| count( $currentChunk ) >= $chunkSizeLimit
					|| $currentWork + $itemWork > $chunkWorkLimit
				)
			) {
				$chunks[]     = $currentChunk;
				$currentChunk = [];
				$currentWork  = 0;
			}

			$currentChunk[ $key ] = $stringData;
			$currentWork          += $itemWork;
			$currentMode           = $itemMode;
		}

		if ( count( $currentChunk ) > 0 ) {
			$chunks[] = $currentChunk;
		}

		return $chunks;
	}

	private function getExpandedWorkForClaimItem( array $stringData ): int {
		$nameCount = isset( $stringData['names'] )
			&& is_array( $stringData['names'] )
			&& count( $stringData['names'] ) > 0
				? count( $stringData['names'] )
				: 1;
		$urlCount = count( $stringData['urls'] );
		$ceiling  = self::CURSOR_CLAIM_WORK_LIMIT + 1;

		if ( $nameCount >= $ceiling || $urlCount >= $ceiling ) {
			return $ceiling;
		}

		$workPerName = $urlCount + 1;
		if ( $nameCount > intdiv( $ceiling, $workPerName ) ) {
			return $ceiling;
		}

		return min( $ceiling, $nameCount * $workPerName );
	}

	private function getChunkFilepath( string $claimFilepath, int $index ): string {
		return $claimFilepath . '.chunk-' . str_pad( (string) ( $index + 1 ), 6, '0', STR_PAD_LEFT ) . '.php';
	}

	private function getSplitChunkFilepaths( string $claimFilepath ): array {
		$filepaths = glob( $claimFilepath . '.chunk-*.php' );

		return is_array( $filepaths ) ? $filepaths : [];
	}

	private function removeUnexpectedSplitChunks( string $claimFilepath, array $expectedFilepaths ): bool {
		$wereRemoved = true;
		foreach ( $this->getSplitChunkFilepaths( $claimFilepath ) as $chunkFilepath ) {
			if (
				! in_array( $chunkFilepath, $expectedFilepaths, true )
				&& ! $this->removeClaimFile( $chunkFilepath )
			) {
				$wereRemoved = false;
			}
		}

		return $wereRemoved;
	}

	private function removeSplitChunks( string $claimFilepath ): bool {
		$wereRemoved = true;
		foreach ( $this->getSplitChunkFilepaths( $claimFilepath ) as $chunkFilepath ) {
			if ( ! $this->removeClaimFile( $chunkFilepath ) ) {
				$wereRemoved = false;
			}
		}

		return $wereRemoved;
	}

	private function removeClaimFile( string $claimFilepath ): bool {
		unset( $this->claimFingerprints[ $claimFilepath ] );
		if ( ! $this->removeClaimProgressFiles( $claimFilepath ) ) {
			return false;
		}

		if ( file_exists( $claimFilepath ) && ! @unlink( $claimFilepath ) ) {
			return false;
		}

		$this->invalidateOpcache( $claimFilepath );
		@unlink( $claimFilepath . '.lock' );

		return true;
	}

	private function releaseClaimLockResource( string $claimFilepath, $claimLock ) {
		$this->fileLock->release( $claimLock );
		unset( $this->claimFingerprints[ $claimFilepath ] );
		@unlink( $claimFilepath . '.lock' );
	}

	private function hasValidPendingStringSchema( array $strings ): bool {
		foreach ( $strings as $string ) {
			if ( ! is_array( $string ) || ! isset( $string['urls'] ) || ! is_array( $string['urls'] ) ) {
				return false;
			}

			if ( isset( $string['names'] ) && ! is_array( $string['names'] ) ) {
				return false;
			}

			if (
				isset( $string['cmp'] )
				&& (
					! is_array( $string['cmp'] )
					|| ! array_key_exists( 0, $string['cmp'] )
					|| ! array_key_exists( 1, $string['cmp'] )
				)
			) {
				return false;
			}

			foreach ( $string['urls'] as $url ) {
				if ( ! is_array( $url ) || ! isset( $url['kind'], $url['url'] ) ) {
					return false;
				}
			}
		}

		return true;
	}

	private function getClaimFilepaths( $domain = null ): array {
		$processingDir = $this->getProcessingDir( false );
		if ( null === $processingDir ) {
			return [];
		}
		if ( null === $domain ) {
			$this->cleanupOrphanClaimLocks( $processingDir );
		}

		$claimFilepaths = glob( $processingDir . '*_pending.php.*.php' );
		if ( ! is_array( $claimFilepaths ) ) {
			return [];
		}

		$claimFilepaths = array_values(
			array_filter(
				$claimFilepaths,
				function( $claimFilepath ) use ( $domain ) {
					return null === $domain || $domain === $this->getDomainFromClaimFilepath( $claimFilepath );
				}
			)
		);
		sort( $claimFilepaths, SORT_STRING );

		return $claimFilepaths;
	}

	private function cleanupOrphanClaimLocks( string $processingDir ) {
		$lockFilepaths = glob( $processingDir . '*_pending.php.*.php.lock' );
		foreach ( is_array( $lockFilepaths ) ? $lockFilepaths : [] as $lockFilepath ) {
			$claimFilepath = substr( $lockFilepath, 0, -strlen( '.lock' ) );
			if ( ! file_exists( $claimFilepath ) ) {
				@unlink( $lockFilepath );
			}
		}

		$progressFilepaths = glob( $processingDir . '*_pending.php.*.php' . self::CLAIM_PROGRESS_SUFFIX );
		foreach ( is_array( $progressFilepaths ) ? $progressFilepaths : [] as $progressFilepath ) {
			$claimFilepath = substr( $progressFilepath, 0, -strlen( self::CLAIM_PROGRESS_SUFFIX ) );
			if ( ! file_exists( $claimFilepath ) ) {
				@unlink( $progressFilepath );
			}
		}

		$tempFilepaths = glob(
			$processingDir . '*_pending.php.*.php' . self::CLAIM_PROGRESS_SUFFIX . '.*.tmp'
		);
		foreach ( is_array( $tempFilepaths ) ? $tempFilepaths : [] as $tempFilepath ) {
			$progressSuffixPosition = strpos( $tempFilepath, self::CLAIM_PROGRESS_SUFFIX );
			$claimFilepath          = false === $progressSuffixPosition
				? ''
				: substr( $tempFilepath, 0, $progressSuffixPosition );
			if ( '' === $claimFilepath || ! file_exists( $claimFilepath ) ) {
				@unlink( $tempFilepath );
			}
		}
	}

	private function getProcessingDir( $create ) {
		$processingDir = rtrim( $this->filesystemRepository->getQueueDir(), '/\\' ) . '/processing/';
		if ( ! is_dir( $processingDir ) && $create ) {
			@mkdir( $processingDir, 0777, true );
		}

		return is_dir( $processingDir ) ? $processingDir : null;
	}

	private function getDomainFromClaimFilepath( string $claimFilepath ) {
		$basename = basename( $claimFilepath );
		$marker   = '_pending.php.';
		$position = strrpos( $basename, $marker );

		return false === $position ? null : substr( $basename, 0, $position );
	}

	private function isClaimFilepath( string $claimFilepath ): bool {
		$processingDir = $this->getProcessingDir( false );

		return null !== $processingDir
			&& 0 === strpos( $claimFilepath, $processingDir )
			&& null !== $this->getDomainFromClaimFilepath( $claimFilepath );
	}

	private function ownsClaim( string $claimId ): bool {
		return isset( $this->claimLocks[ $claimId ] )
			&& $this->isClaimFilepath( $claimId )
			&& file_exists( $claimId );
	}

	private function getDefaultClaimProgress(): array {
		return [
			'version'          => self::CLAIM_PROGRESS_VERSION,
			'completedLocales' => [],
			'diagnostics'      => [],
		];
	}

	private function getClaimProgressFilepath( string $claimId ): string {
		return $claimId . self::CLAIM_PROGRESS_SUFFIX;
	}

	private function getClaimFingerprint( string $claimId ) {
		if ( isset( $this->claimFingerprints[ $claimId ] ) ) {
			return $this->claimFingerprints[ $claimId ];
		}

		$fingerprint = @hash_file( 'sha256', $claimId );
		if (
			is_string( $fingerprint )
			&& '' !== $fingerprint
			&& $this->ownsClaim( $claimId )
		) {
			$this->claimFingerprints[ $claimId ] = $fingerprint;
		}

		return is_string( $fingerprint ) && '' !== $fingerprint ? $fingerprint : null;
	}

	private function hasValidClaimProgressPayload( array $progress ): bool {
		if ( ! isset( $progress['completedLocales'] ) || ! is_array( $progress['completedLocales'] ) ) {
			return false;
		}

		foreach ( $progress['completedLocales'] as $localeIdentity ) {
			if ( ! is_string( $localeIdentity ) || '' === $localeIdentity ) {
				return false;
			}
		}

		if ( isset( $progress['diagnostics'] ) && ! is_array( $progress['diagnostics'] ) ) {
			return false;
		}

		if ( isset( $progress['localeBatch'] ) && ( ! is_string( $progress['localeBatch'] ) || '' === $progress['localeBatch'] ) ) {
			return false;
		}

		if ( ! isset( $progress['workCursor'] ) ) {
			return true;
		}

		$cursor = $progress['workCursor'];

		return is_array( $cursor )
			&& isset( $cursor['keyIndex'], $cursor['nameOffset'], $cursor['phase'], $cursor['urlOffset'] )
			&& is_int( $cursor['keyIndex'] )
			&& is_int( $cursor['nameOffset'] )
			&& is_string( $cursor['phase'] )
			&& is_int( $cursor['urlOffset'] )
			&& $cursor['keyIndex'] >= 0
			&& $cursor['nameOffset'] >= 0
			&& in_array( $cursor['phase'], [ 'strings', 'positions' ], true )
			&& $cursor['urlOffset'] >= 0;
	}

	private function removeClaimProgressFiles( string $claimId ): bool {
		$progressFilepath = $this->getClaimProgressFilepath( $claimId );
		$wereRemoved      = true;

		if ( file_exists( $progressFilepath ) && ! @unlink( $progressFilepath ) ) {
			$wereRemoved = false;
		}

		$tempFilepaths = glob( $progressFilepath . '.*.tmp' );
		foreach ( is_array( $tempFilepaths ) ? $tempFilepaths : [] as $tempFilepath ) {
			if ( file_exists( $tempFilepath ) && ! @unlink( $tempFilepath ) ) {
				$wereRemoved = false;
			}
		}

		return $wereRemoved;
	}

	private function invalidateOpcache( string $filepath ) {
		$restrictApi = (string) ini_get( 'opcache.restrict_api' );
		if (
			function_exists( 'opcache_invalidate' )
			&& ( ! $restrictApi || 0 === stripos( __FILE__, $restrictApi ) )
		) {
			opcache_invalidate( $filepath, true );
		}
	}
}
