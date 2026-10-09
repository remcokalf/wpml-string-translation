<?php

namespace WPML\ST\Shortcode;

use WPML\Collect\Support\Traits\Macroable;
use WPML\FP\Fns;
use WPML\FP\Lst;
use WPML\FP\Obj;
use WPML\FP\Str;
use function WPML\FP\curryN;
use function WPML\FP\pipe;
use function WPML\FP\spreadArgs;

class TranslationHandler {
	use Macroable;

	const SHORTCODE_PATTERN = '/\[wpml-string.*?\[\/wpml-string\]/';

	public static function init() {

		self::macro(
			'appendId',
			curryN(
				2,
				function ( callable $getStringRowByItsDomainAndValue, $fieldData ) {
					$getStringId = spreadArgs( pipe( $getStringRowByItsDomainAndValue, Obj::prop( 'id' ) ) );
					$appendStringId = self::appendToData( pipe( Lst::takeLast( 2 ), $getStringId ) );

					$getShortcode = Lst::nth( 0 );
					$getId = Lst::last();

					$extractDomain = self::firstMatchingGroup( '/context="(.*?)"/', 'wpml-shortcode' );

					$forceRegisterShortcodeString = pipe( $getShortcode, 'do_shortcode' );

					$newShortcode = function ( $data ) use ( $getId, $getShortcode ) {
						$pattern = '/\[wpml-string(.*)\]/';
						$replace = '[wpml-string id="' . $getId( $data ) . '"${1}]';

						return preg_replace( $pattern, $replace, $getShortcode( $data ) );
					};
					$updateSingleShortcode = Fns::converge( Lst::makePair(), [ $getShortcode, $newShortcode ] );

					$updateFieldData = function ( $fieldData, $shortCodePairs ) {
						list( $shortcode, $newShortcode ) = $shortCodePairs;

						return str_replace( $shortcode, $newShortcode, $fieldData );
					};

					return \wpml_collect( Str::matchAll( self::SHORTCODE_PATTERN, $fieldData ) )
					->map( self::appendToData( pipe( $getShortcode, $extractDomain ) ) )
					->map( self::appendToData( pipe( $getShortcode, self::extractInnerText() ) ) )
					->each( $forceRegisterShortcodeString )
					->map( $appendStringId )
					->filter( $getId )
					->map( $updateSingleShortcode )
					->reduce( $updateFieldData, $fieldData );
				}
			)
		);

		self::macro(
			'registerStringTranslation',
			curryN(
				3,
				function ( callable $lens, $data, callable $getTargetLanguage ) {
					$targetLanguage = $getTargetLanguage( $data );
					if ( ! $targetLanguage ) {
						return $data;
					}

					$registerStringTranslation = curryN( 4, 'icl_add_string_translation' );
					$registerStringTranslation = $registerStringTranslation(
						Fns::__,
						$targetLanguage,
						Fns::__,
						ICL_STRING_TRANSLATION_COMPLETE
					);

					global $wpdb;
					$stringMapper = new \WPML_ST_DB_Mappers_Strings( $wpdb );
					$contextScope = [];
					$stringIdsInContext = function ( $context ) use ( $stringMapper, &$contextScope ) {
						if ( ! array_key_exists( $context, $contextScope ) ) {
							$rows                     = (array) $stringMapper->get_all_by_context( $context );
							$contextScope[ $context ] = array_map( 'intval', wpml_collect( $rows )->pluck( 'id' )->toArray() );
						}

						return $contextScope[ $context ];
					};

					$registerTranslationOfSingleString = function ( $shortcodeData ) use ( $registerStringTranslation, $stringIdsInContext ) {
						list( $shortcode, $stringId, $translation ) = $shortcodeData;

						$context      = self::extractShortcodeContext( $shortcode );
						$jobStringIds = $stringIdsInContext( $context );

						if ( ! in_array( (int) $stringId, $jobStringIds, true ) ) {
							return;
						}

						$registerStringTranslation( $stringId, $translation );
					};

					$registerStringsFromFieldData = pipe(
						self::findShortcodesInJobData(),
						Fns::each( $registerTranslationOfSingleString )
					);

					Fns::each( $registerStringsFromFieldData, Obj::view( $lens, $data ) );

					return $data;
				}
			)
		);

		self::macro(
			'restoreOriginalShortcodes',
			curryN(
				3,
				function ( callable $getStringById, callable $lens, $data ) {
					$getOriginalStringValue = pipe( $getStringById, Obj::prop( 'value' ) );
					$restoreSingleShortcode = function ( $fieldData, $shortcodeMatches ) use ( $getOriginalStringValue ) {
						list( , $stringId ) = $shortcodeMatches;

						$pattern     = '/\[wpml-string id="' . preg_quote( $stringId, '/' ) . '"(.*?)\](.*?)\[\/wpml-string\]/';
						$replacement = '[wpml-string${1}]' . $getOriginalStringValue( $stringId ) . '[/wpml-string]';

						$restored = preg_replace( $pattern, $replacement, $fieldData );

						return null === $restored ? $fieldData : $restored;
					};

					$updateFieldData = Fns::map(
						Fns::converge(
							Fns::reduce( $restoreSingleShortcode ),
							[
								Fns::identity(),
								self::findShortcodesInJobData(),
							]
						)
					);

					return Obj::over( $lens, $updateFieldData, $data );
				}
			)
		);
	}

	private static function findShortcodesInJobData() {
		return function ( $str ) {
			$getId     = Lst::nth( 1 );
			$extractId = self::firstMatchingGroup( '/id="(.*?)"/' );

			return \wpml_collect( Str::matchAll( self::SHORTCODE_PATTERN, $str ) )
				->map( self::appendToData( pipe( Lst::nth( 0 ), $extractId ) ) )
				->map( self::appendToData( pipe( Lst::nth( 0 ), self::extractInnerText() ) ) )
				->filter( $getId );
		};
	}

	private static function appendToData( callable $fn ) {
		return Fns::converge( Lst::append(), [ $fn, Fns::identity() ] );
	}

	private static function firstMatchingGroup( $pattern, $fallback = null ) {
		return function ( $str ) use ( $pattern, $fallback ) {
			return Lst::nth( 1, Str::match( $pattern, $str ) ) ?: $fallback;
		};
	}

	private static function extractInnerText() {
		return self::firstMatchingGroup( '/\](.*)\[/' );
	}

	private static function extractShortcodeContext( $shortcode ) {
		$extractContext = self::firstMatchingGroup( '/context="(.*?)"/', 'wpml-shortcode' );

		return $extractContext( (string) $shortcode );
	}
}

TranslationHandler::init();
