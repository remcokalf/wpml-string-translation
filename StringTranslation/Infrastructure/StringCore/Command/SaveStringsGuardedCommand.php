<?php

namespace WPML\StringTranslation\Infrastructure\StringCore\Command;

use WPML\StringTranslation\Application\StringCore\Command\SaveStringsCommandInterface;
use WPML\StringTranslation\Application\StringCore\Command\SaveStringsGuardedCommandInterface;
use WPML\StringTranslation\Application\StringCore\Domain\StringItem;
use WPML\StringTranslation\Application\StringGettext\Repository\StringQuarantineRepositoryInterface;

class SaveStringsGuardedCommand implements SaveStringsGuardedCommandInterface {

	const BISECT_CHUNK_SIZE = 100;

	const RETRY_DELAY_MICROSECONDS = 100000;

	const REASON_INSERT_REJECTED    = 'insert-rejected';
	const REASON_IDENTITY_COLLISION = 'identity-collision';

	private $saveStringsCommand;

	private $quarantineRepository;

	private $wpdb;

	private $lastSaveErrorMessage = '';

	private $lastQuarantineDiagnostic = [
		'count'   => 0,
		'domains' => [],
		'reasons' => [],
	];

	public function __construct(
		SaveStringsCommandInterface $saveStringsCommand,
		StringQuarantineRepositoryInterface $quarantineRepository,
		$wpdb
	) {
		$this->saveStringsCommand   = $saveStringsCommand;
		$this->quarantineRepository = $quarantineRepository;
		$this->wpdb                 = $wpdb;
	}

	public function run( string $domain, array $strings ): array {
		if ( array() === $strings ) {
			return [];
		}

		$this->lastSaveErrorMessage = '';
		$records                    = [];

		list( $clean, $screenRejected ) = $this->screenInvalidText( $strings );
		foreach ( $screenRejected as $string ) {
			$records[] = $this->buildRecord(
				$string,
				self::REASON_INSERT_REJECTED,
				'value contains text invalid for the icl_strings table charset',
				null
			);
		}

		if ( count( $clean ) > 0 ) {
			$this->trySave( $clean );

			$withoutIds = $this->withoutIds( $clean );
			if ( count( $withoutIds ) > 0 ) {
				usleep( self::RETRY_DELAY_MICROSECONDS );
				$this->trySave( $withoutIds );
				$withoutIds = $this->withoutIds( $withoutIds );
			}

			if ( count( $withoutIds ) > 0 ) {
				$records = array_merge( $records, $this->isolateAndClassify( $withoutIds ) );
			}
		}

		if ( count( $records ) > 0 ) {
			$this->quarantineRepository->quarantine( $domain, $records );
			$this->trackDiagnostic( $domain, $records );
		}

		return $this->withIds( $clean );
	}

	public function getLastQuarantineDiagnostic(): array {
		return $this->lastQuarantineDiagnostic;
	}

	private function screenInvalidText( array $strings ): array {
		if ( ! is_object( $this->wpdb ) || ! method_exists( $this->wpdb, 'strip_invalid_text_for_column' ) ) {
			return [ $strings, [] ];
		}

		$table = $this->wpdb->prefix . 'icl_strings';
		$clean = [];
		$bad   = [];

		foreach ( $strings as $string ) {
			$columns = [
				'value'           => (string) $string->getValue(),
				'name'            => (string) $string->getName(),
				'context'         => (string) $string->getDomain(),
				'gettext_context' => (string) $string->getContext(),
			];

			$isClean = true;
			foreach ( $columns as $column => $text ) {
				if ( '' === $text ) {
					continue;
				}
				$stripped = $this->wpdb->strip_invalid_text_for_column( $table, $column, $text );
				if ( $stripped instanceof \WP_Error || (string) $stripped !== $text ) {
					$isClean = false;
					break;
				}
			}

			if ( $isClean ) {
				$clean[] = $string;
			} else {
				$bad[] = $string;
			}
		}

		return [ $clean, $bad ];
	}

	private function isolateAndClassify( array $strings ) {
		$storedValues = $this->getStoredValuesByHash( $strings );

		$suspects = [];
		$records  = [];
		foreach ( $strings as $string ) {
			$hash = (string) $string->getDomainNameContextMd5();
			if ( array_key_exists( $hash, $storedValues ) ) {
				$records[] = $this->buildRecord(
					$string,
					self::REASON_IDENTITY_COLLISION,
					'an icl_strings row with the same domain/name/context already holds a different value',
					$storedValues[ $hash ]
				);
			} else {
				$suspects[] = $string;
			}
		}

		foreach ( array_chunk( $suspects, self::BISECT_CHUNK_SIZE ) as $chunk ) {
			$this->trySave( $chunk );
			foreach ( $this->withoutIds( $chunk ) as $single ) {
				$this->trySave( [ $single ] );
			}
		}

		$stillWithoutIds = $this->withoutIds( $suspects );
		if ( count( $stillWithoutIds ) > 0 ) {
			$lateStoredValues = $this->getStoredValuesByHash( $stillWithoutIds );
			foreach ( $stillWithoutIds as $string ) {
				$hash = (string) $string->getDomainNameContextMd5();
				if ( array_key_exists( $hash, $lateStoredValues ) ) {
					$records[] = $this->buildRecord(
						$string,
						self::REASON_IDENTITY_COLLISION,
						'an icl_strings row with the same domain/name/context already holds a different value',
						$lateStoredValues[ $hash ]
					);
				} else {
					$records[] = $this->buildRecord(
						$string,
						self::REASON_INSERT_REJECTED,
						'the database insert was rejected for this string',
						null
					);
				}
			}
		}

		return $records;
	}

	private function trySave( array $strings ) {
		try {
			$this->saveStringsCommand->run( $strings );
		} catch ( \RuntimeException $e ) {
			$this->lastSaveErrorMessage = $e->getMessage();
		}
	}

	protected function getStoredValuesByHash( array $strings ) {
		$hashes = [];
		foreach ( $strings as $string ) {
			$hash = (string) $string->getDomainNameContextMd5();
			if ( '' !== $hash ) {
				$hashes[ $hash ] = true;
			}
		}
		if ( array() === $hashes || ! is_object( $this->wpdb ) || ! method_exists( $this->wpdb, 'get_results' ) ) {
			return [];
		}

		$wpdb = $this->wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT domain_name_context_md5, value FROM {$wpdb->prefix}icl_strings WHERE domain_name_context_md5 IN ("
				. implode( ', ', array_fill( 0, count( $hashes ), '%s' ) ) . ')',
				...array_keys( $hashes )
			),
			ARRAY_A
		);
		if ( ! is_array( $rows ) ) {
			return [];
		}

		$stored = [];
		foreach ( $rows as $row ) {
			if ( isset( $row['domain_name_context_md5'], $row['value'] ) ) {
				$stored[ (string) $row['domain_name_context_md5'] ] = (string) $row['value'];
			}
		}

		return $stored;
	}

	private function buildRecord( StringItem $string, $reason, $explanation, $storedValue ) {
		$value = (string) $string->getValue();

		return [
			'domain'           => (string) $string->getDomain(),
			'name'             => (string) $string->getName(),
			'gettext_context'  => (string) $string->getContext(),
			'value_b64'        => base64_encode( $value ),
			'value_preview'    => substr( preg_replace( '/[^\x20-\x7E]/', '?', $value ), 0, 120 ),
			'reason'           => (string) $reason,
			'explanation'      => (string) $explanation,
			'stored_value_b64' => null === $storedValue ? null : base64_encode( $storedValue ),
			'db_error'         => $this->getDatabaseErrorForRecord(),
			'timestamp'        => time(),
			'attempts'         => 1,
		];
	}

	private function getDatabaseErrorForRecord() {
		$wpdbError = is_object( $this->wpdb ) && isset( $this->wpdb->last_error ) ? (string) $this->wpdb->last_error : '';

		return '' !== $wpdbError ? $wpdbError : $this->lastSaveErrorMessage;
	}

	private function withIds( array $strings ) {
		return array_values(
			array_filter(
				$strings,
				function ( StringItem $string ) {
					return $string->hasId();
				}
			)
		);
	}

	private function withoutIds( array $strings ) {
		return array_values(
			array_filter(
				$strings,
				function ( StringItem $string ) {
					return ! $string->hasId();
				}
			)
		);
	}

	private function trackDiagnostic( $domain, array $records ) {
		$this->lastQuarantineDiagnostic['count'] += count( $records );

		$domainCount = isset( $this->lastQuarantineDiagnostic['domains'][ $domain ] )
			? $this->lastQuarantineDiagnostic['domains'][ $domain ]
			: 0;
		$this->lastQuarantineDiagnostic['domains'][ $domain ] = $domainCount + count( $records );

		foreach ( $records as $record ) {
			$reason = isset( $record['reason'] ) ? (string) $record['reason'] : 'unknown';

			$reasonCount = isset( $this->lastQuarantineDiagnostic['reasons'][ $reason ] )
				? $this->lastQuarantineDiagnostic['reasons'][ $reason ]
				: 0;
			$this->lastQuarantineDiagnostic['reasons'][ $reason ] = $reasonCount + 1;
		}
	}
}
