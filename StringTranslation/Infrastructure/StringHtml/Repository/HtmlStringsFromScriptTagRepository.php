<?php

namespace WPML\StringTranslation\Infrastructure\StringHtml\Repository;

use WPML\StringTranslation\Application\StringHtml\Repository\HtmlStringsFromScriptTagRepositoryInterface;
use WPML\StringTranslation\Str;

class HtmlStringsFromScriptTagRepository implements HtmlStringsFromScriptTagRepositoryInterface {

	const DEFAULT_OPEN_TAG = '{{{';
	const DEFAULT_CLOSE_TAG = '}}}';
	const DEFAULT_SHORT_OPEN_TAG = '{{';
	const DEFAULT_SHORT_CLOSE_TAG = '}}';
	const UNDERSCORE_OPEN_TAG = '<#';
	const UNDERSCORE_CLOSE_TAG = '#>';
	const EJS_OPEN_TAG = '<%';
	const EJS_CLOSE_TAG = '%>';

	private function hasCustomPlaceholders( string $value, string $placeholderOpenTag, string $placeholderCloseTag ): bool {
		$customPlaceholderStartPos = strpos( $value, $placeholderOpenTag );
		$customPlaceholderEndPos   = strpos( $value, $placeholderCloseTag );
		return (
			$customPlaceholderStartPos !== false &&
			$customPlaceholderEndPos   !== false &&
			$customPlaceholderStartPos < $customPlaceholderEndPos
		);
	}

	private function hasDefaultPlaceholders( string $value ): bool {
		return $this->hasCustomPlaceholders(
			$value,
			self::DEFAULT_OPEN_TAG,
			self::DEFAULT_CLOSE_TAG
		);
	}

	private function hasDefaultShortPlaceholders( string $value ): bool {
		return $this->hasCustomPlaceholders(
			$value,
			self::DEFAULT_SHORT_OPEN_TAG,
			self::DEFAULT_SHORT_CLOSE_TAG
		);
	}

	private function hasUnderscorePlaceholders( string $value ): bool {
		return $this->hasCustomPlaceholders(
			$value,
			self::UNDERSCORE_OPEN_TAG,
			self::UNDERSCORE_CLOSE_TAG
		);
	}

	private function hasEjsPlaceholders( string $value ): bool {
		return $this->hasCustomPlaceholders(
			$value,
			self::EJS_OPEN_TAG,
			self::EJS_CLOSE_TAG
		);
	}

	public function hasCustomPlaceholdersFromAnyJsTemplateEngine( string $value ): bool {
		return (
			$this->hasDefaultPlaceholders( $value ) ||
			$this->hasDefaultShortPlaceholders( $value ) ||
			$this->hasUnderscorePlaceholders( $value ) ||
			$this->hasEjsPlaceholders( $value )
		);
	}

	private function replace( string $openTag, string $closeTag, string $value, string $sep ): string {
		return preg_replace('/' . $openTag . '.*?' . $closeTag . '/s', $sep, $value );
	}

	public function replaceCustomPlaceholdersFromAnyJsTemplateEngineWithHtmlComments( string $value ): string {
		$sep = '<!-- -->';

		if ( $this->hasDefaultPlaceholders( $value ) ) {
			$openTag  = self::DEFAULT_OPEN_TAG;
			$closeTag = self::DEFAULT_CLOSE_TAG;
			return $this->replace( $openTag, $closeTag, $value, $sep );
		}
		if ( $this->hasDefaultShortPlaceholders( $value ) ) {
			$openTag  = self::DEFAULT_SHORT_OPEN_TAG;
			$closeTag = self::DEFAULT_SHORT_CLOSE_TAG;
			return $this->replace( $openTag, $closeTag, $value, $sep );
		}
		if ( $this->hasUnderscorePlaceholders( $value ) ) {
			$openTag  = self::UNDERSCORE_OPEN_TAG;
			$closeTag = self::UNDERSCORE_CLOSE_TAG;
			return $this->replace( $openTag, $closeTag, $value, $sep );
		}
		if ( $this->hasEjsPlaceholders( $value ) ) {
			$openTag  = self::EJS_OPEN_TAG;
			$closeTag = self::EJS_CLOSE_TAG;
			return $this->replace( $openTag, $closeTag, $value, $sep );
		}

		return $value;
	}

	public function removeCustomPlaceholdersFromAnyJsTemplateEngine( string $value ): string {
		$openTag  = self::DEFAULT_OPEN_TAG;
		$closeTag = self::DEFAULT_CLOSE_TAG;
		$value    = $this->replace( $openTag, $closeTag, $value, '' );

		$openTag  = self::DEFAULT_SHORT_OPEN_TAG;
		$closeTag = self::DEFAULT_SHORT_CLOSE_TAG;
		$value    = $this->replace( $openTag, $closeTag, $value, '' );

		$openTag  = self::UNDERSCORE_OPEN_TAG;
		$closeTag = self::UNDERSCORE_CLOSE_TAG;
		$value    = $this->replace( $openTag, $closeTag, $value, '' );

		$openTag  = self::EJS_OPEN_TAG;
		$closeTag = self::EJS_CLOSE_TAG;
		$value    = $this->replace( $openTag, $closeTag, $value, '' );

		return $value;
	}

	private function getOpenTag( string $str ): string {
		$tag = '';
		for ( $i = 1; $i < strlen( $str ); $i++ ) {
			if ( $str[ $i ] === '>' ) {
				break;
			}

			$tag .= $str[ $i ];
		}

		return $tag;
	}

	private function getOpenTagName( string $str ): string {
		$tag = $this->getOpenTag( $str );
		$tagName = '';
		for ( $i = 0; $i < strlen( $tag ); $i++ ) {
			if ( $tag[ $i ] === ' ' ) {
				break;
			}

			$tagName .= $tag[ $i ];
		}

		return trim( $tagName );
	}

	private function getCloseTagName( string $str ): string {
		$tag = '';
		for ( $i = strlen( $str ) - 2; $i >= 0; $i-- ) {
			if ( $str[ $i ] === '/' ) {
				break;
			}

			$tag .= $str[ $i ];
		}

		return trim( strrev( $tag ) );
	}

	public function maybeFixBrokenTags( string $html ): string {
		$html = $this->fixOpenTags( $html );
		return $this->fixCloseTags( $html );
	}

	private function fixOpenTags( string $html ): string {
		$openTag = '/<.*?>/';
		list( $tags, $positions ) = Str::matchWithPositions( $html, $openTag );

		for ( $i = 0; $i < count( $tags ); $i++ ) {
			$tag      = $tags[ $i ];
			$position = $positions[ $i ];

			$openTagName = $this->getOpenTagName( $tag );

			$openTag = $this->getOpenTag( $tag );
			if ( strlen( $openTagName ) > 0 ) {
				continue;
			}

			$fixedTag = '<div' . $openTag . '>';
			$html = Str::removeTextAt( $html, $position, strlen( $tag ) );
			$html = Str::insertTextAt( $html, $position, $fixedTag );
			$positions = array_map( function( $position ) {
				return $position + 3;
			}, $positions );
		}

		return $html;
	}

	private function fixCloseTags( string $html ): string {
		$closeTag = '/<\/.*?>/';
		list( $tags, $positions ) = Str::matchWithPositions( $html, $closeTag );

		for ( $i = 0; $i < count( $tags ); $i++ ) {
			$tag      = $tags[ $i ];
			$position = $positions[ $i ];

			$closeTagName = $this->getCloseTagName( $tag );

			if ( strlen( $closeTagName ) > 0 ) {
				continue;
			}

			$fixedTag = '</div>';
			$html = Str::removeTextAt( $html, $position, strlen( $tag ) );
			$html = Str::insertTextAt( $html, $position, $fixedTag );
			$positions = array_map( function( $position ) {
				return $position + 3;
			}, $positions );
		}

		return $html;
	}
}