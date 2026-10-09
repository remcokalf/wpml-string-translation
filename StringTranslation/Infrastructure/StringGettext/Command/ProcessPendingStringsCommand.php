<?php

namespace WPML\StringTranslation\Infrastructure\StringGettext\Command;

use WPML\Core\SharedKernel\Component\Language\Domain\LanguageCode;
use WPML\ST\MO\Hooks\LanguageSwitch;
use WPML\StringTranslation\Application\Setting\Repository\SettingsRepositoryInterface;
use WPML\StringTranslation\Application\StringCore\Command\LoadExistingStringTranslationsForLocaleCommandInterface;
use WPML\StringTranslation\Application\StringCore\Command\SaveStringPositionsCommandInterface;
use WPML\StringTranslation\Application\StringCore\Command\SaveStringsCommandInterface;
use WPML\StringTranslation\Application\StringCore\Command\UpdateStringTranslationStatusesFromDatabaseCommandInterface;
use WPML\StringTranslation\Application\StringCore\Domain\Factory\StringItemFactory;
use WPML\StringTranslation\Application\StringCore\Domain\StringItem;
use WPML\StringTranslation\Application\StringCore\Domain\StringPosition;
use WPML\StringTranslation\Application\StringCore\Repository\TranslationsRepositoryInterface;
use WPML\StringTranslation\Application\StringGettext\Command\ProcessPendingStringsCommandInterface;
use WPML\StringTranslation\Application\StringCore\Command\SaveStringsGuardedCommandInterface;
use WPML\StringTranslation\Application\StringCore\Command\InsertStringTranslationsGuardedCommandInterface;
use WPML\StringTranslation\Application\StringGettext\Repository\QueueRepositoryInterface;

class ProcessPendingStringsCommand implements ProcessPendingStringsCommandInterface {

	const MAX_STORED_DIAGNOSTICS     = 10;
	const EXPANDED_WORK_BATCH_SIZE   = 1000;
	const LEGACY_EXPANDED_WORK_LIMIT = 1000;
	const STRING_BATCH_SIZE          = 1000;

	private $saveStringsCommand;

	private $translationsRepository;

	private $settingsRepository;

	private $saveStringPositionsCommand;

	private $loadExistingStringTranslationsForLocaleCommand;

	private $updateStringTranslationStatusesFromDatabaseCommand;

	private $stringItemFactory;

	private $queueRepository;

	private $processingBudget;

	private $saveStringsGuarded;

	private $insertTranslationsGuarded;

	public function __construct(
		SaveStringsCommandInterface $saveStringsCommand,
		TranslationsRepositoryInterface $translationsRepository,
		SettingsRepositoryInterface $settingsRepository,
		SaveStringPositionsCommandInterface $saveStringPositionsCommand,
		LoadExistingStringTranslationsForLocaleCommandInterface $loadExistingStringTranslationsForLocaleCommand,
		UpdateStringTranslationStatusesFromDatabaseCommandInterface $updateStringTranslationStatusesFromDatabaseCommand,
		StringItemFactory $stringItemFactory,
		QueueRepositoryInterface $queueRepository,
		PendingQueueProcessingBudget $processingBudget,
		?SaveStringsGuardedCommandInterface $saveStringsGuarded = null,
		?InsertStringTranslationsGuardedCommandInterface $insertTranslationsGuarded = null
	) {
		$this->saveStringsCommand                                 = $saveStringsCommand;
		$this->translationsRepository                             = $translationsRepository;
		$this->settingsRepository                                 = $settingsRepository;
		$this->saveStringPositionsCommand                         = $saveStringPositionsCommand;
		$this->loadExistingStringTranslationsForLocaleCommand     = $loadExistingStringTranslationsForLocaleCommand;
		$this->updateStringTranslationStatusesFromDatabaseCommand = $updateStringTranslationStatusesFromDatabaseCommand;
		$this->stringItemFactory                                  = $stringItemFactory;
		$this->queueRepository                                    = $queueRepository;
		$this->processingBudget                                   = $processingBudget;
		$this->saveStringsGuarded                                 = $saveStringsGuarded;
		$this->insertTranslationsGuarded                          = $insertTranslationsGuarded;
	}

	public function run(): bool {
		return (bool) LanguageSwitch::withPendingQueueProcessing(
			function() {
				return $this->runWithinPendingQueueScope();
			}
		);
	}

	public function getLastDeferralDiagnostic(): array {
		return $this->processingBudget->getLastDeferralDiagnostic();
	}

	private function runWithinPendingQueueScope(): bool {
		$this->processingBudget->start();
		$hasUnavailableClaims = false;

		foreach ( $this->queueRepository->getPendingStringDomainNames() as $domain ) {
			if ( $this->processingBudget->isExhausted() ) {
				return false;
			}

			$claim = $this->queueRepository->claimPendingStringsByDomain( $domain );
			if ( ! isset( $claim['id'], $claim['strings'] ) || ! is_array( $claim['strings'] ) ) {
				$hasUnavailableClaims = true;
				continue;
			}

			$claimId        = $claim['id'];
			$pendingStrings = $this->discardSupersededNamedStrings( $claim['strings'] );
			try {
				if ( ! $this->processDomain( $domain, $claimId, $pendingStrings ) ) {
					return false;
				}

				if ( ! $this->queueRepository->acknowledgePendingStringsClaim( $domain, $claimId, $pendingStrings ) ) {
					return false;
				}
			} finally {
				$this->queueRepository->releasePendingStringsClaim( $claimId );
			}

			unset( $claim );
		}

		return ! $hasUnavailableClaims;
	}

	private function discardSupersededNamedStrings( array $pendingStrings ) : array {
		$ownerByIdentity = [];

		foreach ( array_keys( $pendingStrings ) as $textAndContext ) {
			if (
				! isset( $pendingStrings[ $textAndContext ]['names'] )
				|| ! is_array( $pendingStrings[ $textAndContext ]['names'] )
			) {
				continue;
			}

			list( , $context ) = StringItem::parseTextAndContextKey( $textAndContext );
			foreach ( $pendingStrings[ $textAndContext ]['names'] as $name ) {
				$identity = (string) $name . "\0" . (string) $context;
				if ( isset( $ownerByIdentity[ $identity ] ) ) {
					$previousKey   = $ownerByIdentity[ $identity ];
					$previousNames = array_values(
						array_filter(
							$pendingStrings[ $previousKey ]['names'],
							function( $previousName ) use ( $name ) {
								return (string) $previousName !== (string) $name;
							}
						)
					);

					if ( count( $previousNames ) === 0 ) {
						unset( $pendingStrings[ $previousKey ] );
					} else {
						$pendingStrings[ $previousKey ]['names'] = $previousNames;
					}
				}

				$ownerByIdentity[ $identity ] = $textAndContext;
			}
		}

		return $pendingStrings;
	}

	private function processDomain( string $domain, string $claimId, array $pendingStrings ) : bool {
		if ( count( $pendingStrings ) === 0 ) {
			return true;
		}

		if ( $this->requiresBatchedProcessing( $pendingStrings ) ) {
			return $this->processDomainInBatches( $domain, $claimId, $pendingStrings );
		}

		if ( $this->processingBudget->isExhausted() ) {
			$progress = $this->queueRepository->getPendingStringsClaimProgress( $claimId );
			$this->saveWorkDeferralDiagnostic( $claimId, $progress, $domain, 'post-claim-admission' );
			return false;
		}

		$strings              = [];
		$stringsWithPositions = [];
		foreach ( $pendingStrings as $textAndContext => $stringData ) {
			list( $text, $context ) = StringItem::parseTextAndContextKey( $textAndContext );
			$allStringsForKey       = [];

			if ( isset( $stringData['names'] ) && is_array( $stringData['names'] ) && count( $stringData['names'] ) > 0 ) {
				foreach ( $stringData['names'] as $name ) {
					if ( $this->processingBudget->isExhausted() ) {
						return false;
					}
					$allStringsForKey[] = $this->createString( $stringData, $domain, $text, $name, $context );
				}
			} else {
				$allStringsForKey[] = $this->createString( $stringData, $domain, $text, null, $context );
			}

			foreach ( $stringData['urls'] as $url ) {
				foreach ( $allStringsForKey as $string ) {
					if ( $this->processingBudget->isExhausted() ) {
						return false;
					}
					$string->addPosition(
						new StringPosition(
							$url['kind'],
							$url['url'],
							$string
						)
					);
				}
			}

			if ( isset( $stringData['saveStringInDb'] ) && $stringData['saveStringInDb'] ) {
				foreach ( $allStringsForKey as $string ) {
					$strings[] = $string;
				}
			}
			foreach ( $allStringsForKey as $string ) {
				$stringsWithPositions[] = $string;
			}

			if ( $this->processingBudget->isExhausted() ) {
				return false;
			}
		}

		if ( count( $strings ) > 0 ) {
			$persistedStrings = $this->persistStrings( $domain, $strings );
			$this->assertStringsHaveDatabaseIds( $persistedStrings );
			$stringsWithPositions = $this->withoutQuarantined( $stringsWithPositions, $strings, $persistedStrings );

			if ( count( $persistedStrings ) > 0 ) {
				if ( ! $this->processExistingTranslationsByLocale( $domain, $claimId, $persistedStrings ) ) {
					return false;
				}

				$this->updateStringTranslationStatusesFromDatabaseCommand->run( $persistedStrings );
			}
		}

		if ( count( $stringsWithPositions ) > 0 ) {
			$this->saveStringPositionsCommand->run( $stringsWithPositions );
		}

		return true;
	}

	private function processDomainInBatches( string $domain, string $claimId, array $pendingStrings ) : bool {
		$progress = $this->queueRepository->getPendingStringsClaimProgress( $claimId );
		if ( ! isset( $progress['workCursor'] ) ) {
			$progress = $this->getInitialWorkProgress( $progress );

			if ( ! $this->queueRepository->savePendingStringsClaimProgress( $claimId, $progress ) ) {
				return false;
			}
		} elseif ( ! $this->hasSemanticallyValidWorkProgress( $progress, $pendingStrings ) ) {
			$progress = $this->getInitialWorkProgress( $progress );
			if ( ! $this->queueRepository->savePendingStringsClaimProgress( $claimId, $progress ) ) {
				return false;
			}
		}

		if ( $this->processingBudget->isExhausted() ) {
			$this->saveWorkDeferralDiagnostic( $claimId, $progress, $domain, 'post-claim-admission' );
			return false;
		}

		$keys = array_keys( $pendingStrings );
		while ( true ) {
			$cursor = $progress['workCursor'];
			if ( $cursor['keyIndex'] >= count( $keys ) ) {
				return true;
			}

			$textAndContext = (string) $keys[ $cursor['keyIndex'] ];
			$stringData     = $pendingStrings[ $keys[ $cursor['keyIndex'] ] ];
			$names          = $this->getStringNames( $stringData );
			$urls           = $stringData['urls'];

			if ( $cursor['nameOffset'] >= count( $names ) ) {
				$progress = $this->advanceProgress(
					$progress,
					[
						'keyIndex'   => $cursor['keyIndex'] + 1,
						'nameOffset' => 0,
						'phase'      => 'strings',
						'urlOffset'  => 0,
					]
				);
				if ( ! $this->queueRepository->savePendingStringsClaimProgress( $claimId, $progress ) ) {
					return false;
				}
				continue;
			}

			$nameChunk = array_slice( $names, $cursor['nameOffset'], self::STRING_BATCH_SIZE );
			if ( 'strings' === $cursor['phase'] ) {
				list( $text, $context ) = StringItem::parseTextAndContextKey( $textAndContext );
				$strings                = $this->createStringsForNames( $stringData, $domain, $text, $context, $nameChunk );
				$localeBatch            = null;

				if ( ! empty( $stringData['saveStringInDb'] ) ) {
					$persistedStrings = $this->persistStrings( $domain, $strings );
					$this->assertStringsHaveDatabaseIds( $persistedStrings );
					$strings = $persistedStrings;

					$localeBatch = $this->getLocaleBatchIdentity( $cursor, $strings );
					if (
						! isset( $progress['localeBatch'] )
						|| ! is_string( $progress['localeBatch'] )
						|| ! hash_equals( $localeBatch, $progress['localeBatch'] )
					) {
						$progress['completedLocales'] = [];
						$progress['diagnostics']      = [];
						$progress['localeBatch']      = $localeBatch;
						if ( ! $this->queueRepository->savePendingStringsClaimProgress( $claimId, $progress ) ) {
							return false;
						}
					}

					if ( count( $strings ) > 0 ) {
						if ( ! $this->processExistingTranslationsByLocale( $domain, $claimId, $strings ) ) {
							return false;
						}

						$this->updateStringTranslationStatusesFromDatabaseCommand->run( $strings );
					}
				}

				$nextCursor = count( $urls ) > 0
					? [
						'keyIndex'   => $cursor['keyIndex'],
						'nameOffset' => $cursor['nameOffset'],
						'phase'      => 'positions',
						'urlOffset'  => 0,
					]
					: $this->getCursorAfterNameChunk( $cursor, count( $nameChunk ), count( $names ) );
				$progress   = $this->advanceProgress( $progress, $nextCursor );
				if ( 'positions' === $nextCursor['phase'] && is_string( $localeBatch ) ) {
					$progress['localeBatch'] = $localeBatch;
				}
			} else {
				$urlsPerBatch = max(
					1,
					intdiv( self::EXPANDED_WORK_BATCH_SIZE, max( 1, count( $nameChunk ) ) )
				);
				$urlChunk     = array_slice( $urls, $cursor['urlOffset'], $urlsPerBatch );

				if ( count( $urlChunk ) > 0 ) {
					list( $text, $context ) = StringItem::parseTextAndContextKey( $textAndContext );
					$strings                = $this->createStringsForNames( $stringData, $domain, $text, $context, $nameChunk );
					$localeBatch            = isset( $progress['localeBatch'] ) && is_string( $progress['localeBatch'] )
						? $progress['localeBatch']
						: null;
					foreach ( $urlChunk as $url ) {
						foreach ( $strings as $string ) {
							$string->addPosition(
								new StringPosition(
									$url['kind'],
									$url['url'],
									$string
								)
							);
						}
					}
					$this->saveStringPositionsCommand->run( $strings );

					if ( ! empty( $stringData['saveStringInDb'] ) ) {
						$missingStrings = array_values(
							array_filter(
								$strings,
								function( StringItem $string ) {
									return ! $string->hasId();
								}
							)
						);
						if ( count( $missingStrings ) > 0 ) {
							$persistedMissing = $this->persistStrings( $domain, $missingStrings );
							$this->assertStringsHaveDatabaseIds( $persistedMissing );
						}

						$currentLocaleBatch = $this->getLocaleBatchIdentity( $cursor, $strings );
						if ( null === $localeBatch || ! hash_equals( $currentLocaleBatch, $localeBatch ) ) {
							$progress['completedLocales'] = [];
							$progress['diagnostics']      = [];
							$progress['workCursor']       = [
								'keyIndex'   => $cursor['keyIndex'],
								'nameOffset' => $cursor['nameOffset'],
								'phase'      => 'strings',
								'urlOffset'  => 0,
							];
							$progress['localeBatch']      = $currentLocaleBatch;
							if ( ! $this->queueRepository->savePendingStringsClaimProgress( $claimId, $progress ) ) {
								return false;
							}
							continue;
						}
					}
				}

				$nextUrlOffset = $cursor['urlOffset'] + count( $urlChunk );
				$nextCursor    = $nextUrlOffset < count( $urls )
					? [
						'keyIndex'   => $cursor['keyIndex'],
						'nameOffset' => $cursor['nameOffset'],
						'phase'      => 'positions',
						'urlOffset'  => $nextUrlOffset,
					]
					: $this->getCursorAfterNameChunk( $cursor, count( $nameChunk ), count( $names ) );
				$progress      = $this->advanceProgress( $progress, $nextCursor );
				if ( 'positions' === $nextCursor['phase'] && isset( $localeBatch ) && is_string( $localeBatch ) ) {
					$progress['localeBatch'] = $localeBatch;
				}
			}

			if ( ! $this->queueRepository->savePendingStringsClaimProgress( $claimId, $progress ) ) {
				return false;
			}

			if ( $progress['workCursor']['keyIndex'] >= count( $keys ) ) {
				return false;
			}

			if ( $this->processingBudget->isExhausted() ) {
				return false;
			}
		}
	}

	private function requiresBatchedProcessing( array $pendingStrings ) : bool {
		$totalExpandedWork = 0;
		foreach ( $pendingStrings as $stringData ) {
			$nameCount = $this->getStringNameCount( $stringData );
			$urlCount  = count( $stringData['urls'] );
			$keyWork   = $this->getExpandedWorkUpTo(
				$nameCount,
				$urlCount,
				self::LEGACY_EXPANDED_WORK_LIMIT + 1
			);

			if (
				$nameCount > self::STRING_BATCH_SIZE
				|| $keyWork > self::EXPANDED_WORK_BATCH_SIZE
			) {
				return true;
			}

			$totalExpandedWork += $keyWork;
			if ( $totalExpandedWork > self::LEGACY_EXPANDED_WORK_LIMIT ) {
				return true;
			}
		}

		return false;
	}

	private function getExpandedWorkUpTo( int $nameCount, int $urlCount, int $ceiling ) : int {
		if ( $nameCount >= $ceiling || $urlCount >= $ceiling ) {
			return $ceiling;
		}

		$workPerName = $urlCount + 1;
		if ( $nameCount > intdiv( $ceiling, $workPerName ) ) {
			return $ceiling;
		}

		return min( $ceiling, $nameCount * $workPerName );
	}

	private function getStringNames( array $stringData ) : array {
		if ( isset( $stringData['names'] ) && is_array( $stringData['names'] ) && count( $stringData['names'] ) > 0 ) {
			return $stringData['names'];
		}

		return [ null ];
	}

	private function getStringNameCount( array $stringData ) : int {
		return isset( $stringData['names'] )
			&& is_array( $stringData['names'] )
			&& count( $stringData['names'] ) > 0
				? count( $stringData['names'] )
				: 1;
	}

	private function createStringsForNames(
		array $stringData,
		string $domain,
		string $text,
		?string $context,
		array $names
	) : array {
		$strings = [];
		foreach ( $names as $name ) {
			$strings[] = $this->createString( $stringData, $domain, $text, $name, $context );
		}

		return $strings;
	}

	private function createString(
		array $stringData,
		string $domain,
		string $text,
		?string $name = null,
		?string $context = null
	) : StringItem {
		return $this->stringItemFactory->create(
			$domain,
			$text,
			$context,
			[
				'name'          => $name,
				'componentId'   => isset( $stringData['cmp'] ) ? $stringData['cmp'][0] : null,
				'componentType' => isset( $stringData['cmp'] ) ? $stringData['cmp'][1] : null,
				'stringType'    => StringItem::STRING_TYPE_AUTOREGISTER,
			]
		);
	}

	private function getInitialWorkCursor() : array {
		return [
			'keyIndex'   => 0,
			'nameOffset' => 0,
			'phase'      => 'strings',
			'urlOffset'  => 0,
		];
	}

	private function getInitialWorkProgress( array $progress ) : array {
		$progress['completedLocales'] = [];
		$progress['diagnostics']      = [];
		$progress['workCursor']       = $this->getInitialWorkCursor();
		unset( $progress['localeBatch'] );

		return $progress;
	}

	private function hasSemanticallyValidWorkProgress( array $progress, array $pendingStrings ) : bool {
		if ( ! isset( $progress['workCursor'] ) || ! is_array( $progress['workCursor'] ) ) {
			return false;
		}

		$cursor   = $progress['workCursor'];
		$keys     = array_keys( $pendingStrings );
		$keyCount = count( $keys );
		if ( $cursor['keyIndex'] > $keyCount ) {
			return false;
		}

		if ( $cursor['keyIndex'] === $keyCount ) {
			return 0 === $cursor['nameOffset']
				&& 'strings' === $cursor['phase']
				&& 0 === $cursor['urlOffset']
				&& ! isset( $progress['localeBatch'] );
		}

		$stringData = $pendingStrings[ $keys[ $cursor['keyIndex'] ] ];
		$nameCount  = $this->getStringNameCount( $stringData );
		if (
			$cursor['nameOffset'] >= $nameCount
			|| 0 !== $cursor['nameOffset'] % self::STRING_BATCH_SIZE
		) {
			return false;
		}

		$urlCount = count( $stringData['urls'] );
		if ( 'strings' === $cursor['phase'] ) {
			if ( 0 !== $cursor['urlOffset'] ) {
				return false;
			}
		} else {
			$nameChunkSize = min( self::STRING_BATCH_SIZE, $nameCount - $cursor['nameOffset'] );
			$urlsPerBatch  = max(
				1,
				intdiv( self::EXPANDED_WORK_BATCH_SIZE, (int) max( 1, $nameChunkSize ) )
			);
			if (
				0 === $urlCount
				|| $cursor['urlOffset'] >= $urlCount
				|| 0 !== $cursor['urlOffset'] % $urlsPerBatch
			) {
				return false;
			}
		}

		if ( isset( $progress['localeBatch'] ) ) {
			if ( empty( $stringData['saveStringInDb'] ) ) {
				return false;
			}

			$prefix = 'work-v2:' . $cursor['keyIndex'] . ':' . $cursor['nameOffset'] . ':';
			if (
				0 !== strpos( $progress['localeBatch'], $prefix )
				|| 1 !== preg_match( '/^[a-f0-9]{64}$/', substr( $progress['localeBatch'], strlen( $prefix ) ) )
			) {
				return false;
			}
		} elseif ( 'positions' === $cursor['phase'] && ! empty( $stringData['saveStringInDb'] ) ) {
			return false;
		}

		return true;
	}

	private function getCursorAfterNameChunk( array $cursor, int $chunkSize, int $nameCount ) : array {
		$nextNameOffset = $cursor['nameOffset'] + $chunkSize;
		if ( $nextNameOffset < $nameCount ) {
			return [
				'keyIndex'   => $cursor['keyIndex'],
				'nameOffset' => $nextNameOffset,
				'phase'      => 'strings',
				'urlOffset'  => 0,
			];
		}

		return [
			'keyIndex'   => $cursor['keyIndex'] + 1,
			'nameOffset' => 0,
			'phase'      => 'strings',
			'urlOffset'  => 0,
		];
	}

	private function getLocaleBatchIdentity( array $cursor, array $strings ) : string {
		$ids = [];
		foreach ( $strings as $string ) {
			$ids[] = (string) $string->getId();
		}

		return 'work-v2:'
			. $cursor['keyIndex']
			. ':'
			. $cursor['nameOffset']
			. ':'
			. hash( 'sha256', count( $ids ) . ':' . implode( ',', $ids ) );
	}

	private function advanceProgress( array $progress, array $cursor ) : array {
		$progress['workCursor']       = $cursor;
		$progress['completedLocales'] = [];
		$progress['diagnostics']      = [];
		unset( $progress['localeBatch'] );

		return $progress;
	}

	private function saveWorkDeferralDiagnostic(
		string $claimId,
		array &$progress,
		string $domain,
		string $stage
	) {
		$diagnostic = $this->processingBudget->getLastDeferralDiagnostic();
		if ( ! is_array( $diagnostic ) || count( $diagnostic ) === 0 ) {
			$diagnostic = [
				'reason'    => 'request-time-budget',
				'timestamp' => time(),
			];
		}

		$diagnostic['domain'] = $domain;
		$diagnostic['stage']  = $stage;
		if ( ! isset( $progress['diagnostics'] ) || ! is_array( $progress['diagnostics'] ) ) {
			$progress['diagnostics'] = [];
		}
		$progress['diagnostics'][ 'work|' . $stage ] = $diagnostic;
		if ( count( $progress['diagnostics'] ) > self::MAX_STORED_DIAGNOSTICS ) {
			$progress['diagnostics'] = array_slice(
				$progress['diagnostics'],
				-self::MAX_STORED_DIAGNOSTICS,
				null,
				true
			);
		}

		$this->queueRepository->savePendingStringsClaimProgress( $claimId, $progress );
	}

	private function processExistingTranslationsByLocale( string $domain, string $claimId, array $strings ) : bool {
		$progress            = $this->queueRepository->getPendingStringsClaimProgress( $claimId );
		$completedIdentities = isset( $progress['completedLocales'] ) && is_array( $progress['completedLocales'] )
			? array_fill_keys( $progress['completedLocales'], true )
			: [];
		$domains             = $this->getStringDomains( $strings );
		$releaseDomains      = array_values( array_unique( array_merge( [ 'default' ], $domains ) ) );

		foreach ( $this->getTargetLanguageLocalePairs() as $target ) {
			$identity = $target['languageCode'] . '|' . $target['locale'];
			if ( isset( $completedIdentities[ $identity ] ) ) {
				continue;
			}

			$filePaths = $this->translationsRepository->getTranslationFilepathsForLocale(
				$strings,
				$target['locale']
			);
			if ( ! $this->processingBudget->canStartLocale( $target['locale'], $filePaths, $domains ) ) {
				$this->saveDeferralDiagnostic( $claimId, $progress, $identity, $target, $domain );
				return false;
			}

			try {
				$this->loadExistingStringTranslationsForLocaleCommand->run(
					$strings,
					$target['locale'],
					$target['languageCode']
				);
			} finally {
				LanguageSwitch::releasePendingQueueLocale( $target['locale'], $releaseDomains );
			}

			$progress['completedLocales'][]   = $identity;
			$progress['completedLocales']     = array_values( array_unique( $progress['completedLocales'] ) );
			$completedIdentities[ $identity ] = true;
			if ( isset( $progress['diagnostics'][ $identity ] ) ) {
				unset( $progress['diagnostics'][ $identity ] );
			}

			if ( ! $this->queueRepository->savePendingStringsClaimProgress( $claimId, $progress ) ) {
				return false;
			}

			gc_collect_cycles();
		}

		return true;
	}

	private function saveDeferralDiagnostic(
		string $claimId,
		array &$progress,
		string $identity,
		array $target,
		string $domain
	) {
		$diagnostic = $this->processingBudget->getLastDeferralDiagnostic();
		if ( ! is_array( $diagnostic ) || count( $diagnostic ) === 0 ) {
			$diagnostic = [
				'reason'    => 'request-time-budget',
				'timestamp' => time(),
			];
		}

		$diagnostic['domain']          = $domain;
		$diagnostic['language_code']   = $target['languageCode'];
		$diagnostic['locale_identity'] = $identity;
		if ( ! isset( $progress['diagnostics'] ) || ! is_array( $progress['diagnostics'] ) ) {
			$progress['diagnostics'] = [];
		}
		$progress['diagnostics'][ $identity ] = $diagnostic;
		if ( count( $progress['diagnostics'] ) > self::MAX_STORED_DIAGNOSTICS ) {
			$progress['diagnostics'] = array_slice(
				$progress['diagnostics'],
				-self::MAX_STORED_DIAGNOSTICS,
				null,
				true
			);
		}

		$this->queueRepository->savePendingStringsClaimProgress( $claimId, $progress );
	}

	private function getTargetLanguageLocalePairs(): array {
		$targets = $this->settingsRepository->getActiveSecondaryLanguageLocalePairs();
		if ( ! LanguageCode::isEnglish( $this->settingsRepository->getDefaultLanguageCode() ) ) {
			array_unshift(
				$targets,
				[
					'languageCode' => $this->settingsRepository->getDefaultLanguageCode(),
					'locale'       => $this->settingsRepository->getDefaultLanguageLocaleCode(),
				]
			);
		}

		$validTargets = [];
		foreach ( $targets as $target ) {
			if (
				! isset( $target['languageCode'], $target['locale'] )
				|| ! is_string( $target['languageCode'] )
				|| ! is_string( $target['locale'] )
				|| '' === $target['languageCode']
				|| '' === $target['locale']
			) {
				continue;
			}

			$validTargets[ $target['languageCode'] . '|' . $target['locale'] ] = $target;
		}

		return array_values( $validTargets );
	}

	private function getStringDomains( array $strings ) : array {
		$domains = [];
		foreach ( $strings as $string ) {
			$domains[] = $string->getDomain();
		}

		return array_values( array_unique( $domains ) );
	}

	private function persistStrings( $domain, array $strings ) {
		if ( $this->saveStringsGuarded ) {
			return $this->saveStringsGuarded->run( $domain, $strings );
		}

		$this->saveStringsCommand->run( $strings );

		return $strings;
	}

	private function withoutQuarantined( array $candidates, array $attempted, array $persisted ) {
		$quarantined = [];
		foreach ( $attempted as $string ) {
			if ( ! in_array( $string, $persisted, true ) ) {
				$quarantined[] = $string;
			}
		}
		if ( array() === $quarantined ) {
			return $candidates;
		}

		return array_values(
			array_filter(
				$candidates,
				function ( $candidate ) use ( $quarantined ) {
					return ! in_array( $candidate, $quarantined, true );
				}
			)
		);
	}

	public function getLastQuarantineDiagnostic(): array {
		$merged = [
			'count'   => 0,
			'domains' => [],
			'reasons' => [],
		];

		$sources = [];
		if ( $this->saveStringsGuarded ) {
			$sources[] = $this->saveStringsGuarded->getLastQuarantineDiagnostic();
		}
		if ( $this->insertTranslationsGuarded ) {
			$sources[] = $this->insertTranslationsGuarded->getLastQuarantineDiagnostic();
		}

		foreach ( $sources as $diagnostic ) {
			$merged['count'] += isset( $diagnostic['count'] ) ? (int) $diagnostic['count'] : 0;
			foreach ( [ 'domains', 'reasons' ] as $tally ) {
				if ( ! isset( $diagnostic[ $tally ] ) || ! is_array( $diagnostic[ $tally ] ) ) {
					continue;
				}
				foreach ( $diagnostic[ $tally ] as $key => $countForKey ) {
					$current                    = isset( $merged[ $tally ][ $key ] ) ? $merged[ $tally ][ $key ] : 0;
					$merged[ $tally ][ $key ]   = $current + (int) $countForKey;
				}
			}
		}

		return $merged;
	}

	private function assertStringsHaveDatabaseIds( array $strings ) {
		foreach ( $strings as $string ) {
			if ( ! $string->hasId() ) {
				throw new \RuntimeException(
					'String Translation could not persist a pending string before locale processing.'
				);
			}
		}
	}
}
