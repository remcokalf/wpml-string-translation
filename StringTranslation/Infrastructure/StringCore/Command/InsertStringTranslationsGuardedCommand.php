<?php

namespace WPML\StringTranslation\Infrastructure\StringCore\Command;

use WPML\StringTranslation\Application\StringCore\Command\InsertStringTranslationsCheckedCommandInterface;
use WPML\StringTranslation\Application\StringCore\Command\InsertStringTranslationsGuardedCommandInterface;
use WPML\StringTranslation\Application\StringCore\Domain\StringTranslation;
use WPML\StringTranslation\Application\StringGettext\Repository\StringQuarantineRepositoryInterface;

class InsertStringTranslationsGuardedCommand implements InsertStringTranslationsGuardedCommandInterface {

	const BISECT_CHUNK_SIZE = 100;

	const RETRY_DELAY_MICROSECONDS = 100000;

	const REASON_TRANSLATION_INSERT_REJECTED = 'translation-insert-rejected';

	private $insertStringTranslations;

	private $quarantineRepository;

	private $wpdb;

	private $lastInsertErrorMessage = '';

	private $lastQuarantineDiagnostic = [
		'count'   => 0,
		'domains' => [],
		'reasons' => [],
	];

	public function __construct(
		InsertStringTranslationsCheckedCommandInterface $insertStringTranslations,
		StringQuarantineRepositoryInterface $quarantineRepository,
		$wpdb
	) {
		$this->insertStringTranslations = $insertStringTranslations;
		$this->quarantineRepository     = $quarantineRepository;
		$this->wpdb                     = $wpdb;
	}

	public function run( array $translations ): int {
		if ( array() === $translations ) {
			return 0;
		}

		$this->lastInsertErrorMessage = '';

		list( $clean, $screenRejected ) = $this->screenInvalidText( $translations );

		$inserted = 0;
		$failed   = $screenRejected;

		if ( count( $clean ) > 0 ) {
			if ( $this->tryInsert( $clean ) ) {
				$inserted = count( $clean );
			} else {
				usleep( self::RETRY_DELAY_MICROSECONDS );
				if ( $this->tryInsert( $clean ) ) {
					$inserted = count( $clean );
				} else {
					list( $isolatedInserted, $isolatedFailed ) = $this->isolate( $clean );

					$inserted = $isolatedInserted;
					$failed   = array_merge( $failed, $isolatedFailed );
				}
			}
		}

		if ( count( $failed ) > 0 ) {
			$this->quarantineFailed( $failed );
		}

		return $inserted;
	}

	public function getLastQuarantineDiagnostic(): array {
		return $this->lastQuarantineDiagnostic;
	}

	private function screenInvalidText( array $translations ): array {
		if ( ! is_object( $this->wpdb ) || ! method_exists( $this->wpdb, 'strip_invalid_text_for_column' ) ) {
			return [ $translations, [] ];
		}

		$table = $this->wpdb->prefix . 'icl_string_translations';
		$clean = [];
		$bad   = [];

		foreach ( $translations as $translation ) {
			$value = (string) $translation->getValue();
			if ( '' === $value ) {
				$clean[] = $translation;
				continue;
			}

			$stripped = $this->wpdb->strip_invalid_text_for_column( $table, 'value', $value );
			if ( $stripped instanceof \WP_Error || (string) $stripped !== $value ) {
				$bad[] = $translation;
			} else {
				$clean[] = $translation;
			}
		}

		return [ $clean, $bad ];
	}

	private function isolate( array $translations ) {
		$inserted = 0;
		$failed   = [];

		foreach ( array_chunk( $translations, self::BISECT_CHUNK_SIZE ) as $chunk ) {
			if ( $this->tryInsert( $chunk ) ) {
				$inserted += count( $chunk );
				continue;
			}

			foreach ( $chunk as $single ) {
				if ( $this->tryInsert( [ $single ] ) ) {
					$inserted++;
				} else {
					$failed[] = $single;
				}
			}
		}

		return [ $inserted, $failed ];
	}

	private function tryInsert( array $translations ) {
		try {
			$this->insertStringTranslations->runChecked( $translations );

			return true;
		} catch ( \RuntimeException $e ) {
			$this->lastInsertErrorMessage = $e->getMessage();

			return false;
		}
	}

	private function quarantineFailed( array $failed ) {
		$recordsByDomain = [];
		foreach ( $failed as $translation ) {
			$string = $translation->getString();
			$domain = $string ? (string) $string->getDomain() : '';
			$value  = (string) $translation->getValue();

			$recordsByDomain[ $domain ][] = [
				'domain'           => $domain,
				'name'             => $string ? (string) $string->getName() : '',
				'gettext_context'  => $string ? (string) $string->getContext() : '',
				'language'         => (string) $translation->getLanguage(),
				'value_b64'        => base64_encode( $value ),
				'value_preview'    => substr( preg_replace( '/[^\x20-\x7E]/', '?', $value ), 0, 120 ),
				'source_value_b64' => $string ? base64_encode( (string) $string->getValue() ) : null,
				'reason'           => self::REASON_TRANSLATION_INSERT_REJECTED,
				'explanation'      => 'an existing translation from the catalogs could not be stored for this language',
				'stored_value_b64' => null,
				'db_error'         => $this->getDatabaseErrorForRecord(),
				'timestamp'        => time(),
				'attempts'         => 1,
			];
		}

		foreach ( $recordsByDomain as $domain => $records ) {
			$this->quarantineRepository->quarantine( $domain, $records );
			$this->trackDiagnostic( $domain, $records );
		}
	}

	private function getDatabaseErrorForRecord() {
		$wpdbError = is_object( $this->wpdb ) && isset( $this->wpdb->last_error ) ? (string) $this->wpdb->last_error : '';

		return '' !== $wpdbError ? $wpdbError : $this->lastInsertErrorMessage;
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
