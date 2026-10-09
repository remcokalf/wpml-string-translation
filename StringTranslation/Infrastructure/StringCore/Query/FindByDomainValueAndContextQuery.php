<?php

namespace WPML\StringTranslation\Infrastructure\StringCore\Query;

use WPML\StringTranslation\Application\StringCore\Domain\StringItem;
use WPML\StringTranslation\Application\StringCore\Domain\StringPosition;
use WPML\StringTranslation\Application\StringCore\Query\Criteria\DomainValueAndContextCriteria;
use WPML\StringTranslation\Application\StringCore\Query\FindByDomainValueAndContextQueryInterface;

class FindByDomainValueAndContextQuery implements FindByDomainValueAndContextQueryInterface {

	const EXACT_STRINGS_QUERY_MAX_ITEMS      = 1000;
	const MATCHING_POSITIONS_QUERY_MAX_ITEMS = 100;
	const MATCHING_POSITIONS_QUERY_MAX_BYTES = 32768;

	private $wpdb;

	public function __construct( $wpdb ) {
		$this->wpdb = $wpdb;
	}

	public function execute( DomainValueAndContextCriteria $criteria ) : array {
		$strings = $criteria->getStringsToSearch();
		$fields  = $criteria->getFieldsToHydrate();

		if ( count( $strings ) === 0 ) {
			return [];
		}

		$fetchId                = in_array( 'id', $fields, true );
		$fetchStringType        = in_array( 'string_type', $fields, true );
		$fetchComponentId       = in_array( 'component_id', $fields, true );
		$fetchComponentType     = in_array( 'component_type', $fields, true );
		$fetchPositions         = in_array( 'positions', $fields, true );
		$fetchMatchingPositions = in_array( 'matching_positions', $fields, true );

		$fetchStrings = (
			( $fetchId || $fetchStringType || $fetchComponentId || $fetchComponentType )
		);

		if ( $fetchStrings ) {
			$this->findStringsDataByDomainValueAndContext( $strings, $fetchId, $fetchStringType, $fetchComponentId, $fetchComponentType );
		}

		if ( $fetchPositions ) {
			$this->findPositionsData( $strings );
		} elseif ( $fetchMatchingPositions ) {
			$this->findMatchingPositionsData( $strings );
		}

		return $strings;
	}

	private function findStringsDataByDomainValueAndContext(
		array $allStrings,
		bool $fetchId,
		bool $fetchStringType,
		bool $fetchComponentId,
		bool $fetchComponentType
	) {
		$fieldsSql = '';
		if ( $fetchId ) {
			$fieldsSql .= 'id, ';
		}
		if ( $fetchStringType ) {
			$fieldsSql .= 'string_type, ';
		}
		if ( $fetchComponentId ) {
			$fieldsSql .= 'component_id, ';
		}
		if ( $fetchComponentType ) {
			$fieldsSql .= 'component_type, ';
		}

		$stringsByHash = [];
		foreach ( $allStrings as $string ) {
			$stringsByHash[ $string->getDomainNameContextMd5() ][] = $string;
		}

		$wpdb = $this->wpdb;

		foreach ( array_chunk( array_keys( $stringsByHash ), self::EXACT_STRINGS_QUERY_MAX_ITEMS ) as $hashChunk ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT ' . $fieldsSql
					. "domain_name_context_md5, value FROM {$wpdb->prefix}icl_strings "
					. 'WHERE domain_name_context_md5 IN ('
					. implode( ', ', array_fill( 0, count( $hashChunk ), '%s' ) ) . ')',
					...$hashChunk
				),
				ARRAY_A
			);
			if ( null === $rows ) {
				throw new \RuntimeException( 'String Translation could not prepare an exact string lookup.' );
			}
			if (
				! is_array( $rows )
				|| (
					isset( $this->wpdb->last_error )
					&& is_string( $this->wpdb->last_error )
					&& '' !== $this->wpdb->last_error
				)
			) {
				throw new \RuntimeException( 'String Translation could not load exact string rows for a queue batch.' );
			}

			foreach ( $rows as $row ) {
				$hash = isset( $row['domain_name_context_md5'] )
					? (string) $row['domain_name_context_md5']
					: '';
				if ( '' === $hash || ! isset( $stringsByHash[ $hash ], $row['value'] ) ) {
					continue;
				}

				foreach ( $stringsByHash[ $hash ] as $string ) {
					if ( $string->getValue() !== (string) $row['value'] ) {
						continue;
					}

					if ( $fetchId ) {
						$string->setId( (int) $row['id'] );
					}
					if ( $fetchStringType ) {
						$string->setStringType( (int) $row['string_type'] );
					}
					if ( $fetchComponentId ) {
						$string->setComponentId( $row['component_id'] );
					}
					if ( $fetchComponentType ) {
						$string->setComponentType( (int) $row['component_type'] );
					}
				}
			}
		}
	}

	private function findPositionsData( array $strings ) {
		$stringIds = array_values(
			array_map(
				function( $string ) {
					return (int) $string->getId();
				},
				$strings
			)
		);
		if ( ! $stringIds ) {
			return;
		}

		$wpdb = $this->wpdb;
		$res  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, string_id, kind, position_in_page FROM {$wpdb->prefix}icl_string_positions WHERE string_id IN ("
				. implode( ', ', array_fill( 0, count( $stringIds ), '%d' ) ) . ')',
				...$stringIds
			),
			ARRAY_A
		);
		if ( ! is_array( $res ) ) {
			return;
		}

		$stringById = [];
		foreach ( $strings as $string ) {
			$id = $string->getId();
			if ( null !== $id ) {
				$stringById[ $id ] = $string;
			}
		}

		foreach ( $res as $row ) {
			$stringId = (int) $row['string_id'];
			if ( ! array_key_exists( $stringId, $stringById ) ) {
				continue;
			}

			$string   = $stringById[ $stringId ];
			$position = new StringPosition(
				$row['kind'],
				$row['position_in_page'],
				$string
			);
			$existingPosition = null;
			foreach ( $string->getPositions() as $maybeExistingPosition ) {
				if ( $position->isEqualTo( $maybeExistingPosition ) ) {
					$existingPosition = $maybeExistingPosition;
					break;
				}
			}

			if ( $existingPosition instanceof StringPosition ) {
				$existingPosition->setId( $row['id'] );
			} else {
				$position->setId( $row['id'] );
				$string->addPosition( $position );
			}
		}
	}

	private function findMatchingPositionsData( array $strings ) {
		$positionsByKey = [];
		$searchRows     = [];

		foreach ( $strings as $string ) {
			$stringId = $string->getId();
			if ( null === $stringId ) {
				continue;
			}

			foreach ( $string->getNewPositions() as $position ) {
				$key = $this->getPositionIdentity(
					$stringId,
					$position->getKind(),
					$position->getPositionInPage()
				);
				if ( ! isset( $positionsByKey[ $key ] ) ) {
					$positionsByKey[ $key ] = [];
					$searchRows[ $key ] = [
						(int) $stringId,
						(int) $position->getKind(),
						(string) $position->getPositionInPage(),
					];
				}
				$positionsByKey[ $key ][] = $position;
			}
		}

		$wpdb = $this->wpdb;

		foreach ( $this->getPositionSearchChunks( array_values( $searchRows ) ) as $searchChunk ) {
			$values = [];
			foreach ( $searchChunk as $searchRow ) {
				$values[] = $searchRow[0];
				$values[] = $searchRow[1];
				$values[] = $searchRow[2];
			}

			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT id, string_id, kind, position_in_page FROM '
					. $wpdb->prefix
					. 'icl_string_positions WHERE '
					. implode(
						' OR ',
						array_fill( 0, count( $searchChunk ), '(string_id=%d AND kind=%d AND position_in_page=%s)' )
					),
					...$values
				),
				ARRAY_A
			);
			if (
				! is_array( $rows )
				|| (
					isset( $this->wpdb->last_error )
					&& is_string( $this->wpdb->last_error )
					&& '' !== $this->wpdb->last_error
				)
			) {
				throw new \RuntimeException(
					'String Translation could not check existing string positions before saving a queue batch.'
				);
			}

			foreach ( $rows as $row ) {
				$key = $this->getPositionIdentity(
					(int) $row['string_id'],
					(int) $row['kind'],
					(string) $row['position_in_page']
				);
				if ( ! isset( $positionsByKey[ $key ] ) ) {
					continue;
				}

				foreach ( $positionsByKey[ $key ] as $position ) {
					$position->setId( (int) $row['id'] );
				}
			}
		}
	}

	private function getPositionIdentity( int $stringId, int $kind, string $position ) : string {
		return $stringId . ':' . $kind . ':' . strlen( $position ) . ':' . $position;
	}

	private function searchRowBytes( array $searchRow ) : int {
		return strlen( '(string_id= AND kind= AND position_in_page=\'\')' )
			+ strlen( (string) $searchRow[0] )
			+ strlen( (string) $searchRow[1] )
			+ strlen( $searchRow[2] );
	}

	private function getPositionSearchChunks( array $searchRows ) : array {
		$chunks       = [];
		$currentChunk = [];
		$currentBytes = 0;

		foreach ( $searchRows as $searchRow ) {
			$rowBytes = $this->searchRowBytes( $searchRow )
				+ ( count( $currentChunk ) > 0 ? strlen( ' OR ' ) : 0 );
			if (
				count( $currentChunk ) > 0
				&& (
					count( $currentChunk ) >= self::MATCHING_POSITIONS_QUERY_MAX_ITEMS
					|| $currentBytes + $rowBytes > self::MATCHING_POSITIONS_QUERY_MAX_BYTES
				)
			) {
				$chunks[]     = $currentChunk;
				$currentChunk = [];
				$currentBytes = 0;
				$rowBytes     = $this->searchRowBytes( $searchRow );
			}

			$currentChunk[] = $searchRow;
			$currentBytes  += $rowBytes;
		}

		if ( count( $currentChunk ) > 0 ) {
			$chunks[] = $currentChunk;
		}

		return $chunks;
	}
}
