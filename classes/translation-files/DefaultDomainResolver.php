<?php

namespace WPML\ST\TranslationFile;

class DefaultDomainResolver {

	public static function resolve( array $rows ) {
		$defaultRank = [];
		$copyRank    = [];

		foreach ( $rows as $row ) {
			$key  = self::key( $row );
			$rank = self::rank( $row );

			if ( self::isWordPressRow( $row ) ) {
				$copyRank[ $key ] = max( $rank, isset( $copyRank[ $key ] ) ? $copyRank[ $key ] : 0 );
			} else {
				$defaultRank[ $key ] = max( $rank, isset( $defaultRank[ $key ] ) ? $defaultRank[ $key ] : 0 );
			}
		}

		return array_values(
			array_filter(
				$rows,
				function ( $row ) use ( $defaultRank, $copyRank ) {
					$key = self::key( $row );

					if ( self::isWordPressRow( $row ) ) {
						return self::rank( $row ) > ( isset( $defaultRank[ $key ] ) ? $defaultRank[ $key ] : 0 );
					}

					return self::rank( $row ) >= ( isset( $copyRank[ $key ] ) ? $copyRank[ $key ] : 0 );
				}
			)
		);
	}

	private static function rank( array $row ) {
		if ( StringsRetrieve::hasTranslatedValue( $row ) ) {
			return 2;
		}

		return StringsRetrieve::parseTranslation( $row ) ? 1 : 0;
	}

	private static function isWordPressRow( array $row ) {
		return isset( $row['source_context'] )
			&& 'wordpress' === strtolower( (string) $row['source_context'] );
	}

	private static function key( array $row ) {
		$gettext_context = isset( $row['gettext_context'] ) ? (string) $row['gettext_context'] : '';

		return $row['original'] . StringsRetrieve::KEY_JOIN . $gettext_context;
	}
}
