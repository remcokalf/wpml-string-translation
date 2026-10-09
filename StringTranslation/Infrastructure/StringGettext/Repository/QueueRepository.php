<?php

namespace WPML\StringTranslation\Infrastructure\StringGettext\Repository;

use WPML\StringTranslation\Application\StringGettext\Repository\QueueRepositoryInterface;
use WPML\StringTranslation\Application\StringGettext\Repository\QueueStorageInterface;
use WPML\StringTranslation\Application\Setting\Repository\SettingsRepositoryInterface;
use WPML\StringTranslation\Application\Setting\Repository\UrlRepositoryInterface;
use WPML\StringTranslation\Application\StringCore\Domain\StringItem;
use WPML\StringTranslation\Application\StringCore\Domain\StringPosition;
use WPML\StringTranslation\Application\StringCore\Repository\ComponentRepositoryInterface;
use WPML\StringTranslation\Application\StringGettext\Command\InitStorageCommandInterface;
use WPML\StringTranslation\Application\StringGettext\Command\SavePendingStringsCommandInterface;
use WPML\StringTranslation\Application\StringGettext\Command\SaveProcessedStringsCommandInterface;
use WPML\StringTranslation\Infrastructure\Factory;
use WPML\StringTranslation\Application\StringCore\Domain\Factory\StringItemFactory;

class QueueRepository implements QueueRepositoryInterface {

	const MAX_PENDING_STRINGS_COUNT_FOR_DOMAIN = 30000;

	const DATABASE_VERIFICATION_SAMPLE_SIZE = 10;

	const DATABASE_VERIFICATION_QUERY_MARKER = 'wpml-st gettext queue reconciliation';

	const DATABASE_WATERMARK_QUERY_MARKER = 'wpml-st gettext queue watermark';

	const WATERMARK_KEY = "\0wpml-st:watermark";

	private $currentUrlStrings = [];

	private $processedStrings = [];

	private $pendingStrings = [];

	private $pendingStringsToSave = [];

	private $hasNewPendingStrings = false;

	private $databaseVerifiedDomains = [];

	private $processedWatermarks = [];

	private $wpdb;

	private $factory;

	private $settingsRepository;

	private $componentRepository;

	private $urlRepository;

	private $initStorage;

	private $savePendingStrings;

	private $saveProcessedStrings;

	private $stringItemFactory;

	public function __construct(
		$wpdb,
		Factory                                $factory,
		SettingsRepositoryInterface            $settingsRepository,
		ComponentRepositoryInterface           $componentRepository,
		UrlRepositoryInterface                 $urlRepository,
		InitStorageCommandInterface            $initStorage,
		SavePendingStringsCommandInterface     $savePendingStrings,
		SaveProcessedStringsCommandInterface   $saveProcessedStrings,
		StringItemFactory                      $stringItemFactory
	) {
		$this->wpdb                         = $wpdb;
		$this->factory                      = $factory;
		$this->settingsRepository           = $settingsRepository;
		$this->componentRepository          = $componentRepository;
		$this->urlRepository                = $urlRepository;
		$this->initStorage                  = $initStorage;
		$this->savePendingStrings           = $savePendingStrings;
		$this->saveProcessedStrings         = $saveProcessedStrings;
		$this->stringItemFactory            = $stringItemFactory;
	}

	public function unloadStrings() {
		$this->pendingStrings          = [];
		$this->pendingStringsToSave    = [];
		$this->processedStrings        = [];
		$this->processedWatermarks     = [];
		$this->databaseVerifiedDomains = [];
	}

	private function getStorage() {
		return $this->factory->getGettextStringsQueueStorage();
	}

	public function addCurrentUrlString( string $text, string $domain, ?string $context = null ) {
		$key = $text . $domain . $context;
		$this->currentUrlStrings[ $key ] = [ $text, $domain, $context ];
	}

	public function getCurrentUrlStrings(): array {
		return array_values( $this->currentUrlStrings );
	}

	private function loadDomainProcessedStrings( string $domain ) {
		if ( isset( $this->processedStrings[ $domain ] ) ) {
			return;
		}

		$entries = $this->getStorage()->getProcessedStringsByDomain( $domain );

		$this->processedWatermarks[ $domain ] = $this->extractWatermark( $entries );
		$this->processedStrings[ $domain ]    = $entries;

		$this->reconcileProcessedStringsWithDatabase( $domain );
	}

	private function extractWatermark( array &$entries ) {
		if ( ! array_key_exists( self::WATERMARK_KEY, $entries ) ) {
			return null;
		}

		$watermark = $entries[ self::WATERMARK_KEY ];
		unset( $entries[ self::WATERMARK_KEY ] );

		if ( ! is_array( $watermark ) || ! isset( $watermark['rows'] ) || ! is_numeric( $watermark['rows'] ) ) {
			return null;
		}

		$written = isset( $watermark['written'] ) && is_numeric( $watermark['written'] )
			? (int) $watermark['written']
			: 0;

		return [
			'rows'    => (int) $watermark['rows'],
			'written' => $written,
		];
	}

	private function saveProcessedStringsWithWatermark( string $domain, array $entries, int $rowsBeingRemoved = 0 ) : bool {
		$rows = $this->countDomainStringRows( $domain );

		if ( null === $rows ) {
			$this->processedWatermarks[ $domain ] = null;

			return $this->saveProcessedStrings->run( $domain, $entries );
		}

		$watermark = [
			'rows'    => max( 0, $rows - $rowsBeingRemoved ),
			'written' => time(),
		];
		$this->processedWatermarks[ $domain ] = $watermark;
		$entries[ self::WATERMARK_KEY ]       = $watermark;

		return $this->saveProcessedStrings->run( $domain, $entries );
	}

	private function reconcileProcessedStringsWithDatabase( string $domain ) {
		if ( isset( $this->databaseVerifiedDomains[ $domain ] ) ) {
			return;
		}
		$this->databaseVerifiedDomains[ $domain ] = true;

		$identities = $this->getProcessedStringIdentitySample( $domain );
		if ( count( $identities ) === 0 ) {
			return;
		}

		if (
			! $this->hasDomainLostStringRows( $domain )
			&& $this->countKnownStringIdentities( $identities ) > 0
		) {
			return;
		}

		$this->processedStrings[ $domain ] = [];
		$this->saveProcessedStringsWithWatermark( $domain, [] );
	}

	private function hasDomainLostStringRows( string $domain ) : bool {
		$watermark = isset( $this->processedWatermarks[ $domain ] )
			? $this->processedWatermarks[ $domain ]
			: null;
		if ( null === $watermark ) {
			return false;
		}

		$rows = $this->countDomainStringRows( $domain );

		return null !== $rows && $rows < $watermark['rows'];
	}

	private function countDomainStringRows( string $domain ) {
		$query = 'SELECT COUNT(*) FROM ' . $this->wpdb->prefix . 'icl_strings'
			. ' /* ' . self::DATABASE_WATERMARK_QUERY_MARKER . ' */'
			. ' WHERE context = %s';

		$preparedQuery = $this->wpdb->prepare( $query, $domain );
		if ( ! is_string( $preparedQuery ) || '' === $preparedQuery ) {
			return null;
		}

		$count    = $this->wpdb->get_var( $preparedQuery );
		$hasError = (
			isset( $this->wpdb->last_error )
			&& is_string( $this->wpdb->last_error )
			&& '' !== $this->wpdb->last_error
		);
		if ( null === $count || $hasError ) {
			return null;
		}

		return (int) $count;
	}

	private function getProcessedStringIdentitySample( string $domain ) : array {
		$identities = [];

		foreach ( array_reverse( array_keys( $this->processedStrings[ $domain ] ) ) as $key ) {
			if ( count( $identities ) >= self::DATABASE_VERIFICATION_SAMPLE_SIZE ) {
				break;
			}

			$key = (string) $key;
			list( $text, $context ) = StringItem::parseTextAndContextKey( $key );
			$names = $this->getProcessedStringEntry( $domain, $key, 'names' );
			$name  = count( $names ) > 0 ? (string) reset( $names ) : md5( (string) $text );

			$identities[ md5( $domain . $name . (string) $context ) ] = true;
		}

		return array_keys( $identities );
	}

	private function countKnownStringIdentities( array $identities ) : int {
		$placeholders = implode( ',', array_fill( 0, count( $identities ), '%s' ) );
		$identities_sql = $this->wpdb->prepare( $placeholders, $identities );
		if ( ! is_string( $identities_sql ) || '' === $identities_sql ) {
			return count( $identities );
		}

		$query = 'SELECT COUNT(*) FROM ' . $this->wpdb->prefix . 'icl_strings'
			. ' /* ' . self::DATABASE_VERIFICATION_QUERY_MARKER . ' */'
			. ' WHERE domain_name_context_md5 IN (' . $identities_sql . ')';

		$count    = $this->wpdb->get_var( $query );
		$hasError = (
			isset( $this->wpdb->last_error )
			&& is_string( $this->wpdb->last_error )
			&& '' !== $this->wpdb->last_error
		);
		if ( null === $count || $hasError ) {
			return count( $identities );
		}

		return (int) $count;
	}

	private function loadDomainPendingStrings( string $domain ) {
		if ( isset( $this->pendingStrings[ $domain ] ) ) {
			return;
		}

		$this->pendingStrings[ $domain ] = $this->getStorage()->getPendingStringsByDomain( $domain );
	}

	public function getPendingStringsByDomain( string $domain ): array {
		return $this->getStorage()->getPendingStringsByDomain( $domain );
	}

	private function isProcessedString( string $domain, string $key ): bool {
		return isset( $this->processedStrings[ $domain ][ $key ] );
	}

	private function isPendingString( string $domain, string $key ): bool {
		return isset( $this->pendingStrings[ $domain ][ $key ] );
	}

	private function getProcessedString( string $domain, string $key ): array {
		return $this->isProcessedString( $domain, $key ) ? $this->processedStrings[ $domain ][ $key ] : [];
	}

	private function getPendingString( string $domain, string $key ): array {
		return $this->isPendingString( $domain, $key ) ? $this->pendingStrings[ $domain ][ $key ] : [];
	}

	private function getProcessedStringEntry( string $domain, string $key, string $entryKey ): array {
		$processedString = $this->getProcessedString( $domain, $key );
		$hasEntry        = (
			isset( $processedString[ $entryKey ] ) &&
			is_array( $processedString[ $entryKey ] )
		);

		return $hasEntry ? $processedString[ $entryKey ]: [];
	}

	private function getPendingStringEntry( string $domain, string $key, string $entryKey ): array {
		$pendingString = $this->getPendingString( $domain, $key );
		$hasEntry      = (
			isset( $pendingString[ $entryKey ] ) &&
			is_array( $pendingString[ $entryKey ] )
		);

		return $hasEntry ? $pendingString[ $entryKey ]: [];
	}

	public function isStringAlreadyRegistered( string $text, string $domain, ?string $context = null, ?string $name = null ): bool {
		$key = StringItem::createTextAndContextKey( $text, $context );
		$this->loadDomainProcessedStrings( $domain );
		$this->loadDomainPendingStrings( $domain );

		$isProcessed = $this->isProcessedString( $domain, $key );
		$isPending   = $this->isPendingString( $domain, $key );

		if ( is_string( $name ) ) {
			$isProcessed = in_array( $name, $this->getProcessedStringEntry( $domain, $key, 'names' ) );
			$isPending   = in_array( $name, $this->getPendingStringEntry( $domain, $key, 'names' ) );
		}

		return $isProcessed || $isPending;
	}

	public function canTrackString( string $text, string $domain, ?string $context = null ): bool {
		$key = StringItem::createTextAndContextKey( $text, $context );
		$this->loadDomainProcessedStrings( $domain );
		$this->loadDomainPendingStrings( $domain );

		$isProcessed = $this->isProcessedString( $domain, $key );
		$isPending   = $this->isPendingString( $domain, $key );

		if ( $isProcessed === false && $isPending === false ) {
			return true;
		}

		$maxCount = 5;
		$totalCount = 0;
		if ( $isProcessed ) {
			$totalCount += count( $this->getProcessedStringEntry( $domain, $key, 'urls' ) );
		}
		if ( $isPending ) {
			$totalCount += count( $this->getPendingStringEntry( $domain, $key, 'urls' ) );
		}

		return $totalCount <= $maxCount;
	}

	public function isStringAlreadyTrackedOnUrl( string $text, string $domain, string $requestUrl, ?string $context = null ): bool {
		$key = StringItem::createTextAndContextKey( $text, $context );
		$this->loadDomainProcessedStrings( $domain );
		$this->loadDomainPendingStrings( $domain );

		$isProcessed = $this->isProcessedString( $domain, $key );
		$isPending   = $this->isPendingString( $domain, $key );

		if ( $isProcessed === false && $isPending === false ) {
			return false;
		}

		$isTrackedOnCurrentUrl = in_array( $requestUrl, $this->getProcessedStringEntry( $domain, $key, 'urls' ) );
		$willBeTrackedOnCurrentUrl = in_array(
			$requestUrl,
			array_map(
				function( $item ) {
					return $item['url'];
				},
				$this->getPendingStringEntry( $domain, $key, 'urls' )
			)
		);

		return (
			( $isProcessed && $isTrackedOnCurrentUrl ) ||
			( $isPending && $willBeTrackedOnCurrentUrl )
		);
	}

	public function queueStringAsPending( string $text, string $domain, ?string $context = null, ?string $name = null ): bool {
		$key = StringItem::createTextAndContextKey( $text, $context );
		$this->loadDomainProcessedStrings( $domain );
		$this->loadDomainPendingStrings( $domain );

		if ( count ( $this->pendingStrings[ $domain ] ) >= self::MAX_PENDING_STRINGS_COUNT_FOR_DOMAIN ) {
			return false;
		}

		list( $componentId, $componentType ) = $this->componentRepository->getComponentIdAndType( $text, $domain, $context );

		$this->pendingStrings[ $domain ][ $key ] = [
			'saveStringInDb' => true,
			'cmp' => [
				$componentId,
				$componentType,
			],
			'names' => $this->getPendingStringEntry( $domain, $key, 'names' ),
			'urls'  => $this->getPendingStringEntry( $domain, $key, 'urls' ),
		];
		if ( is_string( $name ) && strlen( $name ) > 0 ) {
			$this->pendingStrings[ $domain ][ $key ]['names'][] = $name;
		}
		$this->markPendingStringForSaving( $domain, $key );

		return $this->hasNewPendingStrings = true;
	}

	public function trackString( string $text, string $domain, string $requestUrl, ?string $context = null ) {
		if ( ! $this->settingsRepository->isStringTrackingEnabled() ) {
			return;
		}

		$key = StringItem::createTextAndContextKey( $text, $context );
		$this->loadDomainProcessedStrings( $domain );
		$this->loadDomainPendingStrings( $domain );

		$url = [
			'kind' => ICL_STRING_TRANSLATION_STRING_TRACKING_TYPE_BACKEND,
			'url'  => $requestUrl,
		];
		if ( $this->urlRepository->getRequestIsAjax() ) {
			$url['kind'] = ICL_STRING_TRANSLATION_STRING_TRACKING_TYPE_AJAX;
		} else if ( $this->urlRepository->getRequestIsRest() ) {
			$url['kind'] = ICL_STRING_TRANSLATION_STRING_TRACKING_TYPE_REST;
		}

		if ( ! isset( $this->pendingStrings[ $domain ][ $key ] ) ) {
			$this->pendingStrings[ $domain ][ $key ] = [];
		}

		$willBeTrackedOnAnotherUrl = (
			count( $this->getPendingStringEntry( $domain, $key, 'urls' ) ) > 0
		);
		$urls = [ $url ];
		if ( $willBeTrackedOnAnotherUrl ) {
			$pendingUrls   = $this->getPendingStringEntry( $domain, $key, 'urls' );
			$pendingUrls[] = $url;
			$urls          = $pendingUrls;
		}

		$this->pendingStrings[ $domain ][ $key ] = array_merge(
			$this->pendingStrings[ $domain ][ $key ],
			[
				'urls' => $urls,
			]
		);
		$this->markPendingStringForSaving( $domain, $key );

		$this->hasNewPendingStrings = true;
	}

	private function markPendingStringForSaving( string $domain, string $key ) {
		$this->pendingStringsToSave[ $domain ][ $key ] = $this->pendingStrings[ $domain ][ $key ];
	}

	public function savePendingStringsQueue() {
		if ( ! $this->hasNewPendingStrings ) {
			return;
		}

		foreach ( $this->pendingStringsToSave as $domain => $pendingStrings ) {
			$this->initStorage->run( $domain );
			if ( $this->savePendingStrings->run( $domain, $pendingStrings ) ) {
				unset( $this->pendingStringsToSave[ $domain ] );
			}
		}

		$this->hasNewPendingStrings = count( $this->pendingStringsToSave ) > 0;
	}

	public function hasPendingStrings(): bool {
		return count( $this->getPendingStringDomainNames() ) > 0;
	}

	public function getPendingStringDomainNames(): array {
		return $this->getStorage()->getPendingStringDomainNames();
	}

	public function claimPendingStringsByDomain( string $domain ): array {
		return $this->getStorage()->claimPendingStringsByDomain( $domain );
	}

	public function getPendingStringsClaimProgress( string $claimId ): array {
		return $this->getStorage()->getPendingStringsClaimProgress( $claimId );
	}

	public function savePendingStringsClaimProgress( string $claimId, array $progress ): bool {
		return $this->getStorage()->savePendingStringsClaimProgress( $claimId, $progress );
	}

	public function releasePendingStringsClaim( string $claimId ) {
		$this->getStorage()->releasePendingStringsClaim( $claimId );
	}

	public function acknowledgePendingStringsClaim( string $domain, string $claimId, array $pendingStrings ): bool {
		$this->loadDomainProcessedStrings( $domain );

		foreach ( $pendingStrings as $textAndContext => $string ) {
			$key = $textAndContext;
			if ( ! isset( $this->processedStrings[ $domain ][ $key ] ) ) {
				$this->processedStrings[ $domain ][ $key ] = [
					'urls'  => [],
					'names' => [],
				];
			}
			$processedUrlKeys  = $this->getProcessedEntryIdentitySet( $domain, $key, 'urls' );
			$processedNameKeys = $this->getProcessedEntryIdentitySet( $domain, $key, 'names' );

			foreach ( $string as $prop => $value ) {
				if ( $prop === 'saveStringInDb' ) {
					continue;
				}

				if ( $prop === 'urls' ) {
					foreach ( $value as $url ) {
						$urlIdentity = $this->getProcessedEntryIdentity( $url['url'] );
						if ( ! isset( $processedUrlKeys[ $urlIdentity ] ) ) {
							$this->processedStrings[ $domain ][ $key ]['urls'][] = $url['url'];
							$processedUrlKeys[ $urlIdentity ]                    = true;
						}
					}
				} else if ( $prop === 'names' ) {
					foreach ( $value as $name ) {
						$nameIdentity = $this->getProcessedEntryIdentity( $name );
						if ( ! isset( $processedNameKeys[ $nameIdentity ] ) ) {
							$this->processedStrings[ $domain ][ $key ]['names'][] = $name;
							$processedNameKeys[ $nameIdentity ]                    = true;
						}
					}
				} else {
					$this->processedStrings[ $domain ][ $key ][ $prop ] = $value;
				}
			}
		}

		$wasSaved = $this->saveProcessedStringsWithWatermark(
			$domain,
			$this->processedStrings[ $domain ]
		);
		unset( $this->processedStrings[ $domain ], $this->processedWatermarks[ $domain ] );

		if ( ! $wasSaved ) {
			return false;
		}

		$wasAcknowledged = $this->getStorage()->acknowledgePendingStringsClaim( $claimId );
		if ( $wasAcknowledged ) {
			unset( $this->pendingStrings[ $domain ] );
		}

		return $wasAcknowledged;
	}

	private function getProcessedEntryIdentitySet( string $domain, string $key, string $entryKey ) : array {
		$identities = [];
		foreach ( $this->getProcessedStringEntry( $domain, $key, $entryKey ) as $value ) {
			$identities[ $this->getProcessedEntryIdentity( $value ) ] = true;
		}

		return $identities;
	}

	private function getProcessedEntryIdentity( $value ) : string {
		$stringValue = (string) $value;

		return strlen( $stringValue ) . ':' . $stringValue;
	}

	public function removeProcessedStrings( array $strings ) {
		$domainsToSave    = [];
		$rowsBeingRemoved = [];

		foreach ( $strings as $string ) {
			$domain = $string->getDomain();
			$this->loadDomainProcessedStrings( $domain );

			$rowsBeingRemoved[ $domain ] = isset( $rowsBeingRemoved[ $domain ] )
				? $rowsBeingRemoved[ $domain ] + 1
				: 1;

			$key = StringItem::createTextAndContextKey( $string->getValue(), $string->getContext() );
			if ( ! array_key_exists( $key, $this->processedStrings[ $domain ] ) ) {
				continue;
			}

			unset( $this->processedStrings[ $domain ][ $key ] );
			$domainsToSave[] = $domain;
		}

		foreach ( array_keys( $rowsBeingRemoved ) as $touchedDomain ) {
			$touchedDomain = (string) $touchedDomain;
			if ( count( $this->processedStrings[ $touchedDomain ] ) > 0 ) {
				$domainsToSave[] = $touchedDomain;
			}
		}

		foreach ( array_unique( $domainsToSave ) as $domain ) {
			$this->saveProcessedStringsWithWatermark(
				$domain,
				$this->processedStrings[ $domain ],
				(int) $rowsBeingRemoved[ $domain ]
			);
			unset( $this->processedStrings[ $domain ], $this->processedWatermarks[ $domain ] );
		}
	}
}
