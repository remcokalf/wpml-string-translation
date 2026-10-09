<?php

namespace WPML\ST\PackageTranslation;

class TranslationStatusSync implements \IWPML_Backend_Action, \IWPML_Frontend_Action, \IWPML_DIC_Action {

	const RESOLVE_CHUNK = 500;

	private $wpdb;

	private $sitepress;

	private $translationManagement;

	private $pending = [];

	private $removedFrom = [];

	private $elementRows = [];

	public function __construct( \wpdb $wpdb, \SitePress $sitepress, \TranslationManagement $translationManagement ) {
		$this->wpdb                  = $wpdb;
		$this->sitepress             = $sitepress;
		$this->translationManagement = $translationManagement;
	}

	public function add_hooks() {
		add_action( 'wpml_st_add_string_translation', [ $this, 'onStringTranslationSaved' ], 10, 4 );
		add_action( 'wpml_st_before_remove_strings', [ $this, 'onStringsRemoved' ] );
	}

	public function onStringTranslationSaved( $stringTranslationId, $translationData, $language, $stringId ) {
		$stringId = (int) $stringId;
		$language = (string) $language;
		if ( ! $stringId || '' === $language ) {
			return;
		}

		$isComplete = is_array( $translationData )
			&& isset( $translationData['status'] )
			&& ICL_TM_COMPLETE === (int) $translationData['status'];

		$this->pending[ $language ][ $stringId ] = ( $this->pending[ $language ][ $stringId ] ?? true ) && $isComplete;

		$this->deferToShutdown();
	}

	public function onStringsRemoved( $stringIds ) {
		$stringIds = array_values( array_filter( array_map( 'intval', (array) $stringIds ) ) );
		if ( ! $stringIds ) {
			return;
		}

		foreach ( $this->resolvePackages( $stringIds ) as $packageId => $package ) {
			$this->removedFrom[ $packageId ] = (object) [
				'id'        => $package->id,
				'kind_slug' => $package->kind_slug,
			];
		}

		if ( $this->removedFrom ) {
			$this->deferToShutdown();
		}
	}

	private function deferToShutdown() {
		if ( ! has_action( 'shutdown', [ $this, 'processQueue' ] ) ) {
			add_action( 'shutdown', [ $this, 'processQueue' ] );
		}
	}

	public function processQueue() {
		remove_action( 'shutdown', [ $this, 'processQueue' ] );

		$pending           = $this->pending;
		$removedFrom       = $this->removedFrom;
		$this->pending     = [];
		$this->removedFrom = [];
		$this->elementRows = [];

		$queue = [];

		foreach ( $pending as $language => $strings ) {
			foreach ( $this->resolvePackages( array_keys( $strings ) ) as $packageId => $package ) {
				$onlyComplete = true;
				foreach ( $package->string_ids as $stringId ) {
					$onlyComplete = $onlyComplete && $strings[ $stringId ];
				}

				$queue[ $packageId ]['package']                          = $package;
				$queue[ $packageId ]['languages'][ (string) $language ] = $onlyComplete;
			}
		}

		foreach ( $removedFrom as $packageId => $package ) {
			$queue[ $packageId ]['package'] = $queue[ $packageId ]['package'] ?? $package;
			foreach ( $this->getTargetLanguages( $package ) as $language ) {
				$queue[ $packageId ]['languages'][ $language ] = false;
			}
		}

		foreach ( $queue as $item ) {
			foreach ( $item['languages'] ?? [] as $language => $onlyComplete ) {
				$this->sync( $item['package'], (string) $language, $onlyComplete );
			}
		}
	}

	private function getTargetLanguages( \stdClass $package ): array {
		$elementType = \WPML_Package_Translation::get_package_element_type( $package->kind_slug );
		$trid        = (int) $this->sitepress->get_element_trid( $package->id, $elementType );
		if ( ! $trid ) {
			return [];
		}

		$elements = $this->getElementRows( $trid );
		if ( ! isset( $elements['source'] ) ) {
			return [];
		}

		$languages = array_merge(
			array_keys( (array) $this->sitepress->get_active_languages() ),
			array_keys( $elements )
		);

		return array_values(
			array_diff(
				array_unique( array_map( 'strval', $languages ) ),
				[ 'source', $elements['source']->language_code ]
			)
		);
	}

	private function sync( \stdClass $package, string $language, bool $onlyComplete ) {
		$elementType = \WPML_Package_Translation::get_package_element_type( $package->kind_slug );
		$trid        = (int) $this->sitepress->get_element_trid( $package->id, $elementType );
		if ( ! $trid ) {
			return;
		}

		$elements = $this->getElementRows( $trid );
		if ( ! isset( $elements['source'] ) || $elements['source']->language_code === $language ) {
			return;
		}

		$target    = $elements[ $language ] ?? null;
		$statusRow = $target ? $this->getStatusRow( (int) $target->translation_id ) : null;

		if ( $statusRow && $this->isJobOpen( (int) $statusRow->status ) ) {
			return;
		}

		if ( $statusRow && $onlyComplete && $this->isComplete( $statusRow ) ) {
			return;
		}

		$derived = $this->deriveStatus( $package->id, $language );
		if ( null === $derived || ICL_TM_NOT_TRANSLATED === $derived['status'] ) {
			if ( $target && $statusRow ) {
				$this->clearStatus( $elementType, $trid, $language, (int) $target->translation_id, $statusRow );
			}

			return;
		}

		if ( $statusRow && $this->isSameStatus( $statusRow, $derived ) ) {
			return;
		}

		$translationId = $target
			? (int) $target->translation_id
			: $this->createTargetElement( $elementType, $trid, $language, $elements['source']->language_code );

		if ( ! $translationId ) {
			return;
		}

		$data = [
			'translation_id' => $translationId,
			'status'         => $derived['status'],
			'needs_update'   => $derived['needs_update'],
		];

		if ( ! $statusRow ) {
			$data['translator_id']       = get_current_user_id();
			$data['translation_service'] = '';
			$data['md5']                 = '';
			$data['links_fixed']         = true;
		}

		$this->translationManagement->update_translation_status( $data );
	}

	private function clearStatus( string $elementType, int $trid, string $language, int $translationId, \stdClass $statusRow ) {
		if ( ! (int) $statusRow->has_job ) {
			$this->sitepress->delete_element_translation( (string) $trid, $elementType, $language, true );
			unset( $this->elementRows[ $trid ][ $language ] );

			return;
		}

		if ( 1 === (int) $statusRow->needs_update ) {
			return;
		}

		$this->translationManagement->update_translation_status(
			[
				'translation_id' => $translationId,
				'needs_update'   => 1,
			]
		);
	}

	private function createTargetElement( string $elementType, int $trid, string $language, string $sourceLanguage ): int {
		$translationId = (int) $this->sitepress->set_element_language_details( null, $elementType, $trid, $language, $sourceLanguage );

		if ( $translationId ) {
			$this->elementRows[ $trid ][ $language ] = (object) [
				'translation_id'       => $translationId,
				'language_code'        => $language,
				'source_language_code' => $sourceLanguage,
			];
		}

		return $translationId;
	}

	private function resolvePackages( array $stringIds ): array {
		$wpdb     = $this->wpdb;
		$packages = [];

		foreach ( array_chunk( $stringIds, self::RESOLVE_CHUNK ) as $chunk ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT s.id AS string_id, p.ID AS id, p.kind_slug
					 FROM {$wpdb->prefix}icl_strings s
					 INNER JOIN {$wpdb->prefix}icl_string_packages p ON p.ID = s.string_package_id
					 WHERE s.id IN (" . implode( ',', array_fill( 0, count( $chunk ), '%d' ) ) . ')',
					$chunk
				)
			);

			foreach ( (array) $rows as $row ) {
				$packageId = (int) $row->id;
				if ( ! isset( $packages[ $packageId ] ) ) {
					$packages[ $packageId ] = (object) [
						'id'         => $packageId,
						'kind_slug'  => $row->kind_slug,
						'string_ids' => [],
					];
				}
				$packages[ $packageId ]->string_ids[] = (int) $row->string_id;
			}
		}

		return $packages;
	}

	private function getElementRows( int $trid ): array {
		if ( isset( $this->elementRows[ $trid ] ) ) {
			return $this->elementRows[ $trid ];
		}

		$wpdb = $this->wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT translation_id, language_code, source_language_code
				 FROM {$wpdb->prefix}icl_translations
				 WHERE trid = %d",
				$trid
			)
		);

		$elements = [];
		foreach ( (array) $rows as $row ) {
			$elements[ $row->language_code ] = $row;
			if ( null === $row->source_language_code ) {
				$elements['source'] = $row;
			}
		}

		$this->elementRows[ $trid ] = $elements;

		return $elements;
	}

	private function getStatusRow( int $translationId ) {
		$wpdb = $this->wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT ts.status, ts.needs_update,
				        EXISTS( SELECT 1 FROM {$wpdb->prefix}icl_translate_job j WHERE j.rid = ts.rid ) AS has_job
				 FROM {$wpdb->prefix}icl_translation_status ts
				 WHERE ts.translation_id = %d",
				$translationId
			)
		);

		return $row ?: null;
	}

	private function isJobOpen( int $status ): bool {
		return in_array(
			$status,
			[
				ICL_TM_WAITING_FOR_TRANSLATOR,
				ICL_TM_IN_PROGRESS,
				ICL_TM_TRANSLATION_READY_TO_DOWNLOAD,
				ICL_TM_ATE_NEEDS_RETRY,
			],
			true
		);
	}

	private function isComplete( \stdClass $statusRow ): bool {
		return ICL_TM_COMPLETE === (int) $statusRow->status && 0 === (int) $statusRow->needs_update;
	}

	private function isSameStatus( \stdClass $statusRow, array $derived ): bool {
		return (int) $statusRow->status === $derived['status']
			&& (int) $statusRow->needs_update === $derived['needs_update'];
	}

	private function deriveStatus( int $packageId, string $language ) {
		$wpdb = $this->wpdb;

		$counts = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(s.id) AS total,
				        COALESCE( SUM( st.status = %d ), 0 ) AS complete,
				        COALESCE( SUM( st.status = %d ), 0 ) AS stale
				 FROM {$wpdb->prefix}icl_strings s
				 LEFT JOIN {$wpdb->prefix}icl_string_translations st
				   ON st.string_id = s.id AND st.language = %s
				 WHERE s.string_package_id = %d",
				ICL_TM_COMPLETE,
				ICL_TM_NEEDS_UPDATE,
				$language,
				$packageId
			)
		);

		$total = $counts ? (int) $counts->total : 0;
		if ( ! $total ) {
			return null;
		}

		$complete   = (int) $counts->complete;
		$stale      = (int) $counts->stale;
		$translated = $complete + $stale;

		if ( ! $translated ) {
			return [
				'status'       => ICL_TM_NOT_TRANSLATED,
				'needs_update' => 0,
			];
		}

		return [
			'status'       => ICL_TM_COMPLETE,
			'needs_update' => ( $translated < $total || $stale > 0 ) ? 1 : 0,
		];
	}
}
