<?php

namespace WPML\StringTranslation\Infrastructure\StringHtml\Repository;

use WPML\StringTranslation\Application\Debug\Service\DebugService;
use WPML\StringTranslation\Application\StringCore\Command\InsertStringsCommandInterface;
use WPML\StringTranslation\Application\StringCore\Domain\StringItem;
use WPML\StringTranslation\Application\StringGettext\Repository\QueueRepositoryInterface;
use WPML\StringTranslation\Application\StringHtml\Repository\GettextStringsRepositoryInterface;
use WPML\StringTranslation\Infrastructure\StringHtml\Command\MatchHtmlStringWithGettextStrings;

class GettextStringsRepository implements GettextStringsRepositoryInterface {
	private $debugService;

	private $queueRepository;

	private $insertStringsCommand;

	private $matchHtmlStringWithGettextStrings;

	public function __construct(
		DebugService $debugService,
		QueueRepositoryInterface $queueRepository,
		InsertStringsCommandInterface $insertStringsCommand,
		MatchHtmlStringWithGettextStrings $matchHtmlStringWithGettextStrings
	) {
		$this->debugService                      = $debugService;
		$this->queueRepository                   = $queueRepository;
		$this->insertStringsCommand              = $insertStringsCommand;
		$this->matchHtmlStringWithGettextStrings = $matchHtmlStringWithGettextStrings;
	}

	private function addHtmlStringsWithConvertedSpecialCharsForSearchInDb( array $htmlStrings ): array {
		$allHtmlStrings = [];
		foreach ( $htmlStrings as $htmlString ) {
			$allHtmlStrings[] = $htmlString;
			$allHtmlStrings[] = htmlentities( $htmlString, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		}

		return $allHtmlStrings;
	}

	private function getAllGettextStringsWithPlaceholdersForHtmlString( array $allGettextStringsWithPlaceholders, string $htmlString ): array {
		return $this->matchHtmlStringWithGettextStrings->run( $allGettextStringsWithPlaceholders, $htmlString );
	}

	private function filterGettextStringsOnlyWithPlaceholders( array $stringModels ): array {
		$filteredStringModels = [];

		foreach ( $stringModels as $stringModel ) {
			if (
				strpos( $stringModel[0], '%' ) !== false ||
				(
					strpos( $stringModel[0], '[' ) !== false &&
					strpos( $stringModel[0], ']' ) !== false
				)
			) {
				$filteredStringModels[] = $stringModel;
			}
		}


		return $filteredStringModels;
	}

	private function filterGettextStringsOnlyWithoutPlaceholders( array $stringModels ): array {
		$filteredStringModels = [];

		foreach ( $stringModels as $stringModel ) {
			if ( strpos( $stringModel[0], '%' ) === false ) {
				$filteredStringModels[] = $stringModel;
			}
		}

		return $filteredStringModels;
	}

	private function groupGettextStringsByStringValue( array $allSourceStrings ): array {
		$byStringValues = [];
		foreach ( $allSourceStrings as $string ) {
			$v = StringItem::filterOnlyTextFromValue( $string[0] );

			if ( ! array_key_exists( $v, $byStringValues ) ) {
				$byStringValues[ $v ] = [];
			}

			$byStringValues[ $v ][] = $string;
		}

		return $byStringValues;
	}

	private function filterOutDuplicateGettextStrings( array $allStrings ): array {
		$stringIds = [];
		$strings   = [];

		foreach ( $allStrings as $string ) {
			$stringId = $string[1] . $string[2] . $string[0];
			if ( in_array( $stringId, $stringIds ) ) {
				continue;
			}

			$stringIds[] = $stringId;
			$strings[]   = $string;
		}

		return $strings;
	}

	private function filterGettextStringsThatCanMatchHtmlStrings( array $gettextStrings, array $htmlStringWords ): array {
		$gettextStringsThatCanMatchByWord = [];

		foreach ( $gettextStrings as $gettextString ) {
			$gettextStringWords = explode( ' ', StringItem::filterOnlyTextFromValue( $gettextString[0] ) );
			foreach ( $htmlStringWords as $htmlStringWord ) {
				if ( in_array( $htmlStringWord, $gettextStringWords ) ) {
					$gettextStringsThatCanMatchByWord[ $htmlStringWord ]   = $gettextStringsThatCanMatchByWord[ $htmlStringWord ] ?? [];
					$gettextStringsThatCanMatchByWord[ $htmlStringWord ][] = $gettextString;
					break;
				}
			}
		}

		return $gettextStringsThatCanMatchByWord;
	}

	private function maybeGetGettextStringWithoutPlaceholderByValue ( $value, $gettextStringsWithoutPlaceholdersByValue ): array {
		return array_key_exists( $value, $gettextStringsWithoutPlaceholdersByValue )
			? $gettextStringsWithoutPlaceholdersByValue[ $value ]
			: [];
	}

	private function matchGettextStringsWithPlaceholders( $htmlString, $wordsFromCurrentHtmlString, $allGettextStringsWithPlaceholdersThatCanMatchByWord ): array {
		$gettextStringsWithPlaceholdersThatCanMatch = [];

		foreach( $wordsFromCurrentHtmlString as $word ) {
			if ( isset( $allGettextStringsWithPlaceholdersThatCanMatchByWord[ $word ] ) ) {
				$gettextStringsWithPlaceholdersThatCanMatch = array_merge(
					$gettextStringsWithPlaceholdersThatCanMatch,
					$allGettextStringsWithPlaceholdersThatCanMatchByWord[ $word ]
				);
			}
		}

		$matchedGettextStringsWithPlaceholders = [];
		if ( count( $gettextStringsWithPlaceholdersThatCanMatch ) > 0 ) {
			$matchedGettextStringsWithPlaceholders = $this->getAllGettextStringsWithPlaceholdersForHtmlString(
				$gettextStringsWithPlaceholdersThatCanMatch,
				$htmlString
			);
		}

		return $matchedGettextStringsWithPlaceholders;
	}

	private $totalPartialMatchesCount = 0;
	const MAX_TOTAL_PARTIAL_MATCHES_COUNT = 100000;

	private function matchGettextStringsWithoutPlaceholdersWithPartialMatch( $wordsFromCurrentHtmlString, $gettextStringsWithoutPlaceholdersByValue ): array {
		if ( $this->totalPartialMatchesCount >= self::MAX_TOTAL_PARTIAL_MATCHES_COUNT ) {
			return [];
		}

		$wordsFromCurrentHtmlStringCount = count( $wordsFromCurrentHtmlString );
		$minWordsCountForPartialMatch = 2;
		$maxWordsCountForPartialMatch = $wordsFromCurrentHtmlStringCount;
		if ( $wordsFromCurrentHtmlStringCount <= 2 || $maxWordsCountForPartialMatch > 25 ) {
			return [];
		}

		$gettextStrings = [];

		$currentPartialMatchStartWordIndexInHtmlString = 0;
		$maxIndex = $wordsFromCurrentHtmlStringCount - 1;
		$loopsCount = 0;
		do {
			$loopsCount++;
			if ( $currentPartialMatchStartWordIndexInHtmlString + $minWordsCountForPartialMatch - 1 > $maxIndex ) {
				break;
			}

			$startIndex = $currentPartialMatchStartWordIndexInHtmlString;
			$partialGettextStrings = [];

			for ( $wordsCount = $minWordsCountForPartialMatch + 1; $wordsCount <= $maxWordsCountForPartialMatch; $wordsCount++ ) {
				$endIndex = $startIndex + $wordsCount - 1;
				if ( $endIndex > $maxIndex ) {
					break;
				}

				$this->totalPartialMatchesCount++;

				$nextStringForPartialMatch = [];
				for ( $i = $startIndex; $i <= $endIndex; $i++ ) {
					$nextStringForPartialMatch[] = $wordsFromCurrentHtmlString[ $i ];
				}
				$nextStringForPartialMatch = implode( ' ', $nextStringForPartialMatch );

				$partialGettextStrings = $this->maybeGetGettextStringWithoutPlaceholderByValue(
					$nextStringForPartialMatch,
					$gettextStringsWithoutPlaceholdersByValue
				);

				if ( count( $partialGettextStrings ) > 0 ) {
					$gettextStrings = array_merge(
						$gettextStrings,
						$partialGettextStrings
					);
					$currentPartialMatchStartWordIndexInHtmlString = $endIndex + 1;
					break;
				}
			}

			if ( count( $partialGettextStrings ) === 0 ) {
				$currentPartialMatchStartWordIndexInHtmlString = $startIndex + 1;
			}
		} while ( true && $loopsCount <= 100 );

		return $gettextStrings;
	}

	private function filterGettextStringsMatchedForHtmlStrings( array $allGettextStrings, array $htmlStrings ): array {
		$htmlStrings = array_values(
			array_unique(
				array_map(
					function( $htmlString ) {
						return StringItem::filterOnlyTextFromValue( $htmlString );
					},
					$htmlStrings
				)
			)
		);

		$gettextStringsWithPlaceholders    = $this->filterGettextStringsOnlyWithPlaceholders( $allGettextStrings );
		$gettextStringsWithoutPlaceholders = $this->filterGettextStringsOnlyWithoutPlaceholders( $allGettextStrings );
		$gettextStringsWithoutPlaceholdersByValue = $this->groupGettextStringsByStringValue( $gettextStringsWithoutPlaceholders );

		$gettextStrings          = [];
		$wordsFromAllHtmlStrings = array_values(
			array_unique(
				explode( ' ', implode( ' ', $htmlStrings ) )
			)
		);
		$allGettextStringsWithPlaceholdersThatCanMatchByWord = $this->filterGettextStringsThatCanMatchHtmlStrings(
			$gettextStringsWithPlaceholders,
			$wordsFromAllHtmlStrings
		);

		foreach ( $htmlStrings as $htmlString ) {
			$wordsFromCurrentHtmlString = explode( ' ', $htmlString );

			$matchedGettextStringsWithoutPlaceholders = $this->maybeGetGettextStringWithoutPlaceholderByValue( $htmlString, $gettextStringsWithoutPlaceholdersByValue );
			$gettextStrings = array_merge(
				$gettextStrings,
				$this->matchGettextStringsWithPlaceholders(
					$htmlString,
					$wordsFromCurrentHtmlString,
					$allGettextStringsWithPlaceholdersThatCanMatchByWord
				),
				$matchedGettextStringsWithoutPlaceholders
			);
			if ( count( $matchedGettextStringsWithoutPlaceholders ) > 0 ) {
				continue;
			}

			$gettextStrings = array_merge(
				$gettextStrings,
				$this->matchGettextStringsWithoutPlaceholdersWithPartialMatch(
					$wordsFromCurrentHtmlString,
					$gettextStringsWithoutPlaceholdersByValue
				)
			);
		}

		return $gettextStrings;
	}

	public function filterOnlyGettextStringsThatMatchesHtmlStrings( array $allGettextStrings, array $htmlStrings ): array {
		$htmlStrings    = $this->addHtmlStringsWithConvertedSpecialCharsForSearchInDb( $htmlStrings );
		$gettextStrings = $this->filterGettextStringsMatchedForHtmlStrings( $allGettextStrings, $htmlStrings );

		return $this->filterOutDuplicateGettextStrings( $gettextStrings );
	}
}