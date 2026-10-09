<?php

namespace WPML\StringTranslation\Infrastructure\StringGettext\Command;

class PendingQueueProcessingBudget {

	const TIME_LIMIT = 25;

	const MEMORY_RESERVE_RATIO         = 0.25;
	const MINIMUM_MEMORY_RESERVE_BYTES = 33554432;
	const MAXIMUM_MEMORY_RESERVE_BYTES = 67108864;

	const CATALOG_MEMORY_EXPANSION_FACTOR = 8.0;
	const MINIMUM_KNOWN_LOCALE_WORK_BYTES = 8388608;
	const UNKNOWN_LOCALE_WORK_BYTES       = 50331648;

	private $startTime = 0.0;

	private $startMemoryUsage = 0;

	private $hasLoggedMemoryExhaustion = false;

	private $lastDeferralDetails = [];

	public function start() {
		$now                             = microtime( true );
		$this->startTime                 = $this->getRequestStartTime( $now );
		$this->startMemoryUsage          = memory_get_usage( true );
		$this->hasLoggedMemoryExhaustion = false;
		$this->lastDeferralDetails       = [];
	}

	public function isExhausted(): bool {
		$timeLimit = (int) apply_filters( 'wpml_st_pending_queue_time_limit', self::TIME_LIMIT );
		if ( microtime( true ) - $this->startTime >= $timeLimit ) {
			$this->deferForTime();
			return true;
		}

		$memoryLimit   = $this->getMemoryLimit();
		$memoryUsage   = memory_get_usage( true );
		$memoryReserve = $this->getMemoryReserve( $memoryLimit );
		if ( $memoryLimit > 0 && $memoryUsage + $memoryReserve >= $memoryLimit ) {
			$this->deferForMemory(
				[
					'reason'                       => 'request-memory-reserve',
					'memory_usage_bytes'           => $memoryUsage,
					'memory_limit_bytes'           => $memoryLimit,
					'response_reserve_bytes'       => $memoryReserve,
					'estimated_locale_work_bytes'  => 0,
					'catalog_bytes'                => 0,
					'catalog_paths_known'          => false,
					'catalog_paths_complete'       => false,
					'projected_memory_bytes'       => $memoryUsage + $memoryReserve,
					'request_start_memory_bytes'   => $this->startMemoryUsage,
					'fresh_projected_memory_bytes' => $this->startMemoryUsage + $memoryReserve,
					'retry_fresh_request'          => $this->startMemoryUsage + $memoryReserve < $memoryLimit,
				]
			);
			return true;
		}

		return false;
	}

	public function canStartLocale( $locale, array $filePaths = [], array $domains = [], $catalogPathsComplete = false ) {
		if ( $this->isTimeExhausted() ) {
			$this->deferForTime();
			return false;
		}

		$memoryLimit = $this->getMemoryLimit();
		if ( $memoryLimit <= 0 ) {
			return true;
		}

		$memoryUsage    = memory_get_usage( true );
		$memoryReserve  = $this->getMemoryReserve( $memoryLimit );
		$catalog        = $this->estimateCatalogMemory( $filePaths, (bool) $catalogPathsComplete );
		$projected      = $memoryUsage + $memoryReserve + $catalog['working_set_bytes'];
		$freshProjected = $this->startMemoryUsage + $memoryReserve + $catalog['working_set_bytes'];

		if ( $projected < $memoryLimit ) {
			return true;
		}

		$this->deferForMemory(
			[
				'reason'                       => 'locale-memory-admission',
				'locale'                       => (string) $locale,
				'domains'                      => array_values( array_unique( array_map( 'strval', $domains ) ) ),
				'memory_usage_bytes'           => $memoryUsage,
				'memory_limit_bytes'           => $memoryLimit,
				'response_reserve_bytes'       => $memoryReserve,
				'estimated_locale_work_bytes'  => $catalog['working_set_bytes'],
				'catalog_bytes'                => $catalog['catalog_bytes'],
				'catalog_paths_known'          => $catalog['paths_known'],
				'catalog_paths_complete'       => $catalog['paths_complete'],
				'projected_memory_bytes'       => $projected,
				'request_start_memory_bytes'   => $this->startMemoryUsage,
				'fresh_projected_memory_bytes' => $freshProjected,
				'retry_fresh_request'          => $freshProjected < $memoryLimit,
			]
		);

		return false;
	}

	public function getLastDeferralDiagnostic() {
		return $this->lastDeferralDetails;
	}

	public function getLastDeferralDetails() {
		return $this->getLastDeferralDiagnostic();
	}

	private function getRequestStartTime( $now ) {
		foreach ( [ 'REQUEST_TIME_FLOAT', 'REQUEST_TIME' ] as $key ) {
			if ( ! isset( $_SERVER[ $key ] ) || ! is_numeric( $_SERVER[ $key ] ) ) {
				continue;
			}

			$requestStartTime = (float) $_SERVER[ $key ];
			if ( $requestStartTime > 0 && $requestStartTime <= $now ) {
				return $requestStartTime;
			}
		}

		return $now;
	}

	private function isTimeExhausted() {
		$timeLimit = (int) apply_filters( 'wpml_st_pending_queue_time_limit', self::TIME_LIMIT );

		return microtime( true ) - $this->startTime >= $timeLimit;
	}

	private function deferForTime() {
		$memoryLimit   = $this->getMemoryLimit();
		$memoryUsage   = memory_get_usage( true );
		$memoryReserve = $this->getMemoryReserve( $memoryLimit );

		$this->lastDeferralDetails = [
			'reason'                       => 'request-time-budget',
			'memory_usage_bytes'           => $memoryUsage,
			'memory_limit_bytes'           => $memoryLimit,
			'response_reserve_bytes'       => $memoryReserve,
			'estimated_locale_work_bytes'  => 0,
			'catalog_bytes'                => 0,
			'catalog_paths_known'          => false,
			'catalog_paths_complete'       => false,
			'projected_memory_bytes'       => $memoryUsage + $memoryReserve,
			'request_start_memory_bytes'   => $this->startMemoryUsage,
			'fresh_projected_memory_bytes' => $this->startMemoryUsage + $memoryReserve,
			'retry_fresh_request'          => true,
			'timestamp'                    => time(),
		];
	}

	private function getMemoryLimit() {
		return (int) wp_convert_hr_to_bytes( (string) ini_get( 'memory_limit' ) );
	}

	private function getMemoryReserve( $memoryLimit ) {
		if ( $memoryLimit <= 0 ) {
			return 0;
		}

		$defaultReserve = (int) min(
			self::MAXIMUM_MEMORY_RESERVE_BYTES,
			max( self::MINIMUM_MEMORY_RESERVE_BYTES, self::MEMORY_RESERVE_RATIO * $memoryLimit )
		);

		$reserve = (int) apply_filters(
			'wpml_st_pending_queue_memory_reserve_bytes',
			$defaultReserve,
			$memoryLimit
		);

		return max( 0, min( $memoryLimit, $reserve ) );
	}

	private function estimateCatalogMemory( array $filePaths, $catalogPathsComplete ) {
		$catalogBytes = 0;
		$knownPaths   = [];

		foreach ( $filePaths as $filePath ) {
			if (
				! is_string( $filePath )
				|| '' === $filePath
				|| isset( $knownPaths[ $filePath ] )
				|| ! is_file( $filePath )
				|| ! is_readable( $filePath )
			) {
				continue;
			}

			$fileSize = filesize( $filePath );
			if ( false === $fileSize ) {
				continue;
			}

			$knownPaths[ $filePath ] = true;
			$catalogBytes           += max( 0, (int) $fileSize );
		}

		if ( count( $knownPaths ) === 0 ) {
			$workingSet = (int) apply_filters(
				'wpml_st_pending_queue_unknown_locale_work_bytes',
				self::UNKNOWN_LOCALE_WORK_BYTES
			);

			return [
				'catalog_bytes'     => 0,
				'working_set_bytes' => max( 0, $workingSet ),
				'paths_known'       => false,
				'paths_complete'    => false,
			];
		}

		$expansionFactor = max(
			1.0,
			(float) apply_filters(
				'wpml_st_pending_queue_catalog_memory_expansion_factor',
				self::CATALOG_MEMORY_EXPANSION_FACTOR
			)
		);
		$workingSet      = (int) ceil( $catalogBytes * $expansionFactor );
		if ( ! $catalogPathsComplete ) {
			$workingSet = max( self::UNKNOWN_LOCALE_WORK_BYTES, $workingSet );
		}

		return [
			'catalog_bytes'     => $catalogBytes,
			'working_set_bytes' => max( self::MINIMUM_KNOWN_LOCALE_WORK_BYTES, $workingSet ),
			'paths_known'       => true,
			'paths_complete'    => (bool) $catalogPathsComplete,
		];
	}

	private function deferForMemory( array $details ) {
		$details['timestamp']      = time();
		$this->lastDeferralDetails = $details;

		if (
			$this->hasLoggedMemoryExhaustion
			|| ! defined( 'WP_DEBUG' )
			|| ! WP_DEBUG
		) {
			return;
		}

		$this->hasLoggedMemoryExhaustion = true;
		$megabyte                        = 1024 * 1024;
		$locale                          = isset( $details['locale'] ) && '' !== $details['locale']
			? '; locale: ' . $details['locale']
			: '';

		error_log(
			sprintf(
				'[WPML String Translation][Info] Pending strings queue processing was deferred for memory safety (%s%s). Usage: %.2f MB; estimated locale work: %.2f MB; response reserve: %.2f MB; projected: %.2f MB; limit: %.2f MB; catalog paths known: %s. The queue remains available for a later request.',
				$details['reason'],
				$locale,
				$details['memory_usage_bytes'] / $megabyte,
				$details['estimated_locale_work_bytes'] / $megabyte,
				$details['response_reserve_bytes'] / $megabyte,
				$details['projected_memory_bytes'] / $megabyte,
				$details['memory_limit_bytes'] / $megabyte,
				$details['catalog_paths_known'] ? 'yes' : 'no'
			)
		);
	}
}
