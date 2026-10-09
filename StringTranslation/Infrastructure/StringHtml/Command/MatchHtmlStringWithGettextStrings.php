<?php

namespace WPML\StringTranslation\Infrastructure\StringHtml\Command;

use WPML\StringTranslation\Infrastructure\StringGettext\Command\ParseStringTextAndPlaceholders;
use WPML\StringTranslation\Application\StringCore\Domain\StringItem;

class MatchHtmlStringWithGettextStrings {

	private $parseStringTextAndPlaceholders;

	public function __construct(
		ParseStringTextAndPlaceholders $parseStringTextAndPlaceholders
	) {
		$this->parseStringTextAndPlaceholders = $parseStringTextAndPlaceholders;
	}

	public function run( array $gettextStrings, string $htmlString ): array {
		$matchedGettextStrings = [];

		foreach ( $gettextStrings as $gettextString ) {
			if ( $this->doesHtmlStringMatchGettextString( $gettextString, $htmlString ) ) {
				$matchedGettextStrings[] = $gettextString;
				continue;
			}

			$value                     = StringItem::filterOnlyTextFromValue( $gettextString[0] );
			$customPlaceholderStartPos = strpos( $value, '[' );
			$customPlaceholderEndPos   = strpos( $value, ']' );
			$hasCustomPlaceholder      = (
				$customPlaceholderStartPos !== false &&
				$customPlaceholderEndPos   !== false &&
				$customPlaceholderStartPos < $customPlaceholderEndPos
			);

			if ( ! $hasCustomPlaceholder ) {
				continue;
			}

			$pattern              = '/(?<!\[ )\[([^\[\]]+)\]/';
			$hasCustomPlaceholder = preg_match($pattern, $value, $matches);
			if ( ! $hasCustomPlaceholder ) {
				continue;
			}

			$gettextStringCopy    = $gettextString;
			$gettextStringCopy[0] = preg_replace('/\[.*?\]/', '%s', $gettextStringCopy[0] );
			if ( $this->doesHtmlStringMatchGettextString( $gettextStringCopy, $htmlString ) ) {
				$matchedGettextStrings[] = $gettextString;
			}
		}

		return $matchedGettextStrings;
	}

	private function doesHtmlStringMatchGettextString( array $gettextString, string $htmlString ): bool {
		$nodes = $this->parseStringTextAndPlaceholders->run( StringItem::filterOnlyTextFromValue( $gettextString[0] ) );
		$nodes = $this->parseTextNodes( $nodes, $htmlString );

		if ( is_null( $nodes ) ) {
			return false;
		}

		$nodes = $this->parsePlaceholderNodes( $nodes, $htmlString );

		$placeholdersCount         = 0;
		$notEmptyPlaceholdersCount = 0;
		$emptyPlaceholdersCount    = 0;
		foreach ( $nodes as $node ) {
			if ( $node['type'] !== 'placeholder' ) {
				continue;
			}
			$placeholdersCount++;

			$words = array_values( array_filter( explode( ' ', $node['htmlStringText'] ) ) );
			if ( count( $words ) === 0 ) {
				$emptyPlaceholdersCount++;
			} else {
				$notEmptyPlaceholdersCount++;
			}
		}

		if ( $placeholdersCount === 0 ) {
			$gettextStringWordsCount = count( explode( ' ', StringItem::filterOnlyTextFromValue( $gettextString[0] ) ) );
			$htmlStringWordsCount    = count( explode( ' ', $htmlString ) );
			return ( $gettextStringWordsCount >= 2 ) || ( $gettextStringWordsCount === 1 && $htmlStringWordsCount <= 3 );
		}

		if ( $emptyPlaceholdersCount > 0 && $notEmptyPlaceholdersCount >= 0 ) {
			$words = explode( ' ', $htmlString );
			return count( $words ) >= 3;
		}

		return $emptyPlaceholdersCount === 0;
	}

	private function parseTextNodes( array $nodes, string $htmlString ) {
		$lastMatchPos = -1;
		$lastMatchStr = '';
		for ( $i = 0; $i < count( $nodes ); $i++ ) {
			$node = $nodes[ $i ];
			if ( $node['type'] !== 'text' ) {
				continue;
			}

			$minSearchOffset = ( $lastMatchPos !== -1 ) ? $lastMatchPos + strlen( $lastMatchStr ) - 1 : 0;
			$matchPos        = strpos( $htmlString, $node['text'], $minSearchOffset );
			if ( $matchPos === false || $matchPos < $lastMatchPos ) {
				return null;
			}

			$nodes[ $i ]['offsetInHtmlString']    = $matchPos;
			$nodes[ $i ]['offsetEndInHtmlString'] = $matchPos + strlen( $node['text'] ) - 1;
			$nodes[ $i ]['htmlStringText']        = $node['text'];
			$lastMatchPos = $matchPos;
			$lastMatchStr = $node['text'];
		}

		return $nodes;
	}

	private function parsePlaceholderNodes( array $nodes, string $htmlString ): array {
		$lastTextNode = null;
		for ( $i = 0; $i < count( $nodes ); $i++ ) {
			$node = $nodes[ $i ];
			if ( $node['type'] === 'text' ) {
				$lastTextNode = $node;
				continue;
			}

			$nextTextNode = null;
			for ( $j = $i + 1; $j < count( $nodes ); $j++ ) {
				if ( $nodes[ $j ]['type'] === 'text' ) {
					$nextTextNode = $nodes[ $j ];
					break;
				}
			}

			$nodes[ $i ]['offsetInHtmlString'] = $lastTextNode ? $lastTextNode['offsetEndInHtmlString'] + 1 : 0;
			$nodes[ $i ]['offsetEndInHtmlString'] = $nextTextNode ? $nextTextNode['offsetInHtmlString'] - 1 : strlen( $htmlString ) - 1;
			$nodes[ $i ]['htmlStringText'] = substr(
				$htmlString,
				$nodes[ $i ]['offsetInHtmlString'],
				$nodes[ $i ]['offsetEndInHtmlString'] - $nodes[ $i ]['offsetInHtmlString'] + 1
			);
		}

		return $nodes;
	}
}