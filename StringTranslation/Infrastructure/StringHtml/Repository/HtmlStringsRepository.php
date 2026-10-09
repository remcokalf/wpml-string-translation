<?php

namespace WPML\StringTranslation\Infrastructure\StringHtml\Repository;

use WPML\StringTranslation\Application\StringHtml\Repository\HtmlStringsRepositoryInterface;
use WPML\StringTranslation\Application\StringHtml\Validator\IsExcludedHtmlStringValidatorInterface;
use WPML\FP\Str;
use WPML\StringTranslation\Application\StringHtml\Repository\HtmlStringsFromScriptTagRepositoryInterface;

class HtmlStringsRepository implements HtmlStringsRepositoryInterface {

	private $htmlStringsFromScriptTagRepository;

	private $isExcludedHtmlStringValidator;

	public function __construct(
		HtmlStringsFromScriptTagRepositoryInterface $htmlStringsFromScriptTagRepository,
		IsExcludedHtmlStringValidatorInterface      $isExcludedHtmlStringValidator
	) {
		$this->htmlStringsFromScriptTagRepository = $htmlStringsFromScriptTagRepository;
		$this->isExcludedHtmlStringValidator      = $isExcludedHtmlStringValidator;
	}

	private function loadHtml( string $html ) {
		$dom = new \DOMDocument();
		$dom->encoding = 'utf-8';

		if ( trim( $html ) === '' ) {
			return $dom;
		}

		$prevErrors = libxml_use_internal_errors(true);
		$dom->loadHTML( $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOWARNING );
		$errors = libxml_get_errors();
		libxml_clear_errors();
		libxml_use_internal_errors( $prevErrors );

		return $dom;
	}

	private function rmNodes( $nodes ) {
		for ( $i = $nodes->length - 1; $i >= 0; $i-- ) {
			$nodes[$i]->parentNode->removeChild( $nodes[ $i ] );
		}
	}

	private function rmCssAndRedundantHtml( \DOMXPath $xpath ) {
		$adminNodes  = $xpath->query("//*[@id='wpadminbar']" );
		$styleNodes  = $xpath->query('//style' );
		$qmNodes     = $xpath->query( "//*[@id='query-monitor-main']" );

		$this->rmNodes( $adminNodes );
		$this->rmNodes( $styleNodes );
		$this->rmNodes( $qmNodes );
	}

	private function rmHeadAndScriptHtml( \DOMXPath $xpath ) {
		$scriptNodes = $xpath->query('//script' );
		$headNodes   = $xpath->query('//head' );

		$this->rmNodes( $scriptNodes );
		$this->rmNodes( $headNodes );
	}

	private function isNodeTextValid( string $nodeText ): bool {
		$length = strlen( $nodeText );
		return $length <= 1000 && $length > 0;
	}

	private function getAllTextTokensFromHtml( \DOMXPath $xpath ): array {
		$textTokens    = [];
		$textNodes     = [];
		$allTextNodes  = $xpath->query("//text()" );
		foreach ( $allTextNodes as $textNode ) {
			$nodeText = $textNode->nodeValue;
			if ( ! $this->isNodeTextValid( $nodeText ) ) {
				continue;
			}
			$nodeText = trim( $nodeText );
			if ( in_array( $nodeText, $textTokens ) ) {
				continue;
			}
			$textNodes[]  = $textNode;
			$textTokens[] = $nodeText;
		}

		$dataAttrs = $xpath->query( "//@*[starts-with(name(), 'data-')]" );
		foreach ( $dataAttrs as $dataAttr ) {
			if ( in_array( 'id', explode( '-', $dataAttr->name ) ) ) {
				continue;
			}
			$text = trim( $dataAttr->value );

			if ( ! in_array( $text, $textTokens ) ) {
				$textTokens[] = $text;
			}
		}

		$inputTokens = [];
		$inputNodes  = $xpath->query('//input' );
		foreach ( $inputNodes as $inputNode ) {
			$inputTokens[] = trim( $inputNode->getAttribute('value') );
			$inputTokens[] = trim( $inputNode->getAttribute('placeholder') );
		}
		$inputNodes  = $xpath->query('//textarea' );
		foreach ( $inputNodes as $inputNode ) {
			$inputTokens[] = trim( $inputNode->getAttribute('value') );
		}
		$selectNodes = $xpath->query('//select');
		foreach ($selectNodes as $selectNode) {
			$optionNodes = $xpath->query( './/option', $selectNode );
			foreach ( $optionNodes as $optionNode ) {
				$inputTokens[] = trim( $optionNode->textContent );
			}
		}

		$tokens = array_merge( $textTokens, $inputTokens );
		$tokens = array_filter(
			$tokens,
			function( $token ) {
				return $this->isExcludedHtmlStringValidator->validate( $token );
			}
		);

		return [ $tokens, $textNodes ];
	}

	private function getTextFromParentNodes( array $textNodes ): array {
		$parentTextTokens = [];
		foreach ( $textNodes as $textNode ) {
			$text = $textNode->parentNode->parentNode->textContent;
			if ( in_array( $textNode->nodeName, [ 'html', 'head', 'body' ] ) ) {
				continue;
			}
			if ( ! $this->isNodeTextValid( $text ) ) {
				continue;
			}

			$text = trim( $text );
			$text = preg_replace( '/\s+/', ' ', $text );

			$wordsCount = count( explode( ' ', $text ) );

			if ( ! in_array( $text, $parentTextTokens ) && $wordsCount > 1 ) {
				$parentTextTokens[] = $text;
			}
		}

		return $parentTextTokens;
	}

	private function getAllTextTokensFromHtmlWithTextFromParentNode( \DOMXPath $xpath ): array {
		list( $textTokens, $textNodes ) = $this->getAllTextTokensFromHtml( $xpath );
		$parentTextTokens = $this->getTextFromParentNodes( $textNodes );

		return array_merge(
			$textTokens,
			$parentTextTokens
		);
	}

	private function readAllRawGettextStringsFromHtmlScriptTags( \DOMDocument $dom, string $html ): array {
		$pattern = '/<script.*?type="text\/(?:html|template)".*?>([\s\S]*?)<\/script>/i';
		preg_match_all( $pattern, $html, $matches );
		$scriptHtmls = [];
		foreach ( $matches[1] as $match ) {
			$scriptHtmls[] = $match;
		}

		$allTextTokens = [];

		foreach ( $scriptHtmls as $scriptHtml ) {
			if ( $this->htmlStringsFromScriptTagRepository->hasCustomPlaceholdersFromAnyJsTemplateEngine( $scriptHtml ) ) {
				$scriptHtml = $this->htmlStringsFromScriptTagRepository->replaceCustomPlaceholdersFromAnyJsTemplateEngineWithHtmlComments(
					$scriptHtml
				);
			}

			$scriptDom   = $this->loadHtml( $scriptHtml );
			$scriptXpath = new \DOMXPath( $scriptDom );
			$textTokens  = $this->getAllTextTokensFromHtmlWithTextFromParentNode( $scriptXpath );

			$allTextTokens = array_merge(
				$allTextTokens,
				$textTokens
			);
		}

		foreach ( $scriptHtmls as $scriptHtml ) {
			$scriptHtml = $this->htmlStringsFromScriptTagRepository->removeCustomPlaceholdersFromAnyJsTemplateEngine( $scriptHtml );
			$scriptHtml = $this->htmlStringsFromScriptTagRepository->maybeFixBrokenTags( $scriptHtml );

			$scriptDom   = $this->loadHtml( $scriptHtml );
			$scriptXpath = new \DOMXPath( $scriptDom );
			$textTokens  = $this->getAllTextTokensFromHtmlWithTextFromParentNode( $scriptXpath );

			$allTextTokens = array_merge(
				$allTextTokens,
				$textTokens
			);
		}

		return $allTextTokens;
	}

	private function readAllRawGettextStringsFromHtml( \DOMDocument $dom, string $html ): array {
		$xpath = new \DOMXPath( $dom );
		$this->rmCssAndRedundantHtml( $xpath );

		$scriptTextTokens = $this->readAllRawGettextStringsFromHtmlScriptTags( $dom, $html );

		$this->rmHeadAndScriptHtml( $xpath );
		$textTokens = $this->getAllTextTokensFromHtmlWithTextFromParentNode( $xpath );

		$textTokens = array_unique(
			array_merge(
				$textTokens,
				$scriptTextTokens
			)
		);

		return $textTokens;
	}

	private function readAllGettextStringsFromHtml( \DOMDocument $dom, string $html ): array {
		return $this->readAllRawGettextStringsFromHtml( $dom, $html );
	}

	public function getAllStringsFromHtml( string $html ): array {
		$htmlStrings = [];

		if ( strlen( $html ) === 0 ) {
			return $htmlStrings;
		}

		$dom = $this->loadHtml( $html );
		$htmlStrings = array_values( $this->readAllGettextStringsFromHtml( $dom, $html ) );

		for ( $i = 0; $i < count( $htmlStrings ); $i++ ) {
			$htmlStrings[ $i ] = str_replace( '"', '', $htmlStrings[ $i ] );
		}

		$uniqueHtmlStrings = [];
		foreach ( $htmlStrings as $htmlString ) {
			if ( in_array( $htmlString, $uniqueHtmlStrings ) ) {
				continue;
			}

			$uniqueHtmlStrings[] = $htmlString;
		}
		$htmlStrings = $uniqueHtmlStrings;

		return $htmlStrings;
	}
}