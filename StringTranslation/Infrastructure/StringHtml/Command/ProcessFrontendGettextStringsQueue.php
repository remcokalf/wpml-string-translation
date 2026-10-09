<?php

namespace WPML\StringTranslation\Infrastructure\StringHtml\Command;

use WPML\StringTranslation\Application\StringHtml\Command\ProcessFrontendGettextStringsQueueInterface;
use WPML\StringTranslation\Application\StringGettext\Repository\FrontendQueueRepositoryInterface;
use WPML\StringTranslation\Application\StringHtml\Command\ProcessFrontendStringsObserverInterface;
use WPML\StringTranslation\Infrastructure\StringGettext\Command\PendingQueueProcessingBudget;

class ProcessFrontendGettextStringsQueue implements ProcessFrontendGettextStringsQueueInterface {

	const MAX_URL_GROUPS_PER_BATCH = 25;

	private $wpdb;

	private $frontendQueueRepository;

	private $budget;

	private $observers;

	public function __construct(
		$wpdb,
		FrontendQueueRepositoryInterface $frontendQueueRepository,
		PendingQueueProcessingBudget $budget,
		array $observers = []
	) {
		$this->wpdb                    = $wpdb;
		$this->frontendQueueRepository = $frontendQueueRepository;
		$this->budget                  = $budget;
		$this->observers               = $observers;
	}

	public function run(): int {
		$allGettextStringsByUrl = $this->frontendQueueRepository->get();
		if ( count( $allGettextStringsByUrl ) === 0 ) {
			return 0;
		}

		$maxUrlGroups = $this->getMaxUrlGroupsPerBatch();
		$this->budget->start();

		$processedCount = 0;
		foreach ( $allGettextStringsByUrl as $gettextStringsByUrl ) {
			if ( $processedCount > 0 && ( $processedCount >= $maxUrlGroups || $this->budget->isExhausted() ) ) {
				break;
			}

			$this->runBatchByRequestUrl( $gettextStringsByUrl->getStrings(), $gettextStringsByUrl->getRequestUrl() );
			$processedCount++;
		}

		$this->frontendQueueRepository->removeProcessed( $processedCount );

		return $processedCount;
	}

	private function getMaxUrlGroupsPerBatch(): int {
		$maxUrlGroups = (int) apply_filters(
			'wpml_st_frontend_queue_url_groups_per_batch',
			self::MAX_URL_GROUPS_PER_BATCH
		);

		return max( 1, $maxUrlGroups );
	}

	private function runBatchByRequestUrl( array $gettextStrings, string $requestUrl ) {
		if ( count( $gettextStrings ) === 0 ) {
			return;
		}

		$wpdb       = $this->wpdb;
		$searchArgs = [];
		foreach ( $gettextStrings as $string ) {
			$hasContext   = is_string( $string->getContext() ) && strlen( $string->getContext() ) > 0;
			$searchArgs[] = $string->getValue();
			$searchArgs[] = $string->getDomain();
			$searchArgs[] = $hasContext ? 1 : 0;
			$searchArgs[] = $hasContext ? $string->getContext() : '';
		}
		$stringIds = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}icl_strings WHERE "
				. implode( ' OR ', array_fill( 0, count( $gettextStrings ), '(value=%s AND context=%s AND (%d = 0 OR gettext_context=%s))' ) ),
				$searchArgs[0],
				$searchArgs[1],
				$searchArgs[2],
				...array_slice( $searchArgs, 3 )
			)
		);

		if ( count( $stringIds ) === 0 ) {
			return;
		}

		$kind = ICL_STRING_TRANSLATION_STRING_TRACKING_TYPE_FRONTEND;

		$stringIdsToUpdate = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT(string_id) AS string_id FROM {$wpdb->prefix}icl_string_positions WHERE string_id IN (" . implode( ', ', array_fill( 0, count( $stringIds ), '%d' ) ) . ')',
				array_map( 'intval', $stringIds )
			)
		);
		$stringIdsToInsert = array_filter(
			$stringIds,
			function( $stringId ) use ( $stringIdsToUpdate ) {
				return ! in_array( $stringId, $stringIdsToUpdate );
			}
		);

		if ( count( $stringIdsToUpdate ) > 0 ) {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->prefix}icl_string_positions SET kind = %d WHERE string_id IN (" . implode( ', ', array_fill( 0, count( $stringIdsToUpdate ), '%d' ) ) . ')',
					array_merge( [ $kind ], array_map( 'intval', $stringIdsToUpdate ) )
				)
			);
		}
		if ( count( $stringIdsToInsert ) > 0 ) {
			$rowsSql = [];
			$args    = [];
			foreach ( $stringIdsToInsert as $stringId ) {
				$rowsSql[] = '(%d, %d, %s)';
				$args[]    = (int) $stringId;
				$args[]    = (int) $kind;
				$args[]    = (string) $requestUrl;
			}
			$wpdb->query(
				$wpdb->prepare(
					"INSERT INTO {$wpdb->prefix}icl_string_positions (string_id, kind, position_in_page) VALUES "
					. implode( ',', array_fill( 0, count( $rowsSql ), '(%d, %d, %s)' ) ),
					$args[0],
					$args[1],
					...array_slice( $args, 2 )
				)
			);
		}

		foreach ( $this->observers as $observer ) {
			$observer->newFrontendStringsRegistered( $stringIds );
		}
	}
}
