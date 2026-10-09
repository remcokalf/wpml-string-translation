<?php

namespace WPML\StringTranslation\Infrastructure\TranslateEverything;

use WPML\Core\SharedKernel\Component\Language\Domain\LanguageCode;

class EnglishSourceLanguage {

	const BASE = LanguageCode::ENGLISH;

	const CATALOGUE_PROBE = [ '\WPML\LanguageEditor\LanguageCodeResolution', 'isEnglish' ];

	private static $siteCache = [];

	private static $englishProbe = null;

	public static function resolve( array $activeCodes, string $defaultCode ): string {
		$activeCodes = array_map( 'strval', $activeCodes );
		if ( in_array( self::BASE, $activeCodes, true ) ) {
			return self::BASE;
		}

		$variants = array_values( array_filter( $activeCodes, [ self::class, 'isEnglishVariant' ] ) );
		sort( $variants );

		if ( [] === $variants ) {
			return self::BASE;
		}

		return in_array( $defaultCode, $variants, true ) ? $defaultCode : $variants[0];
	}

	public static function normalize( string $code, array $activeCodes, string $defaultCode ): string {
		if ( ! LanguageCode::isEnglish( $code ) || in_array( $code, $activeCodes, true ) ) {
			return $code;
		}

		return self::resolve( $activeCodes, $defaultCode );
	}

	public static function resolveForSite(): string {
		global $sitepress;

		if ( ! is_object( $sitepress ) || ! method_exists( $sitepress, 'get_active_languages' ) ) {
			return self::BASE;
		}

		$activeCodes = array_keys( (array) $sitepress->get_active_languages() );
		$defaultCode = (string) $sitepress->get_default_language();
		$key         = implode( ',', $activeCodes ) . '|' . $defaultCode;

		if ( ! array_key_exists( $key, self::$siteCache ) ) {
			self::$siteCache[ $key ] = self::resolve( $activeCodes, $defaultCode );
		}

		return self::$siteCache[ $key ];
	}

	public static function flushCache() {
		self::$siteCache    = [];
		self::$englishProbe = null;
	}

	public static function setEnglishProbeForTesting( ?callable $probe = null ) {
		self::$englishProbe = $probe;
		self::$siteCache    = [];
	}

	private static function isEnglishVariant( string $code ): bool {
		if ( LanguageCode::isEnglish( $code ) ) {
			return true;
		}

		$probe = self::englishProbe();

		return null !== $probe && (bool) call_user_func( $probe, $code );
	}

	private static function englishProbe() {
		if ( null !== self::$englishProbe ) {
			return self::$englishProbe;
		}

		return class_exists( self::CATALOGUE_PROBE[0] ) ? self::CATALOGUE_PROBE : null;
	}
}
