<?php

namespace WPML\ST\TranslationFile;

use WPML\Collect\Support\Collection;
use WPML\ST\TranslateWpmlString;

class StringsRetrieve {

	const KEY_JOIN = '::JOIN::';

	private $string_retrieve;

	public function __construct( \WPML\ST\DB\Mappers\StringsRetrieve $string_retrieve ) {
		$this->string_retrieve = $string_retrieve;
	}


	public function get( $domain, $language, $modified_mo_only ) {
		return $this->loadFromDb( $language, $domain, $modified_mo_only )
					->filter(
						function ( $string ) {
							return (bool) $string['translation'];
						}
					)
					->mapToGroups(
						function ( array $string ) {
							return $this->groupPluralFormsOfSameString( $string );
						}
					)
					->map(
						function ( Collection $strings, $key ) {
							return $this->buildStringEntity( $strings, $key );
						}
					)
					->values()
					->toArray();
	}

	private function loadFromDb( $language, $domain, $modified_mo_only = false ) {
		$rows = $this->string_retrieve->get( $language, $domain, $modified_mo_only );

		if ( 'default' === strtolower( (string) $domain ) ) {
			$rows = DefaultDomainResolver::resolve( $rows );
		}

		$result = \wpml_collect( $rows );

		return $result->map(
			function ( $row ) {
				return $this->parseResult( $row );
			}
		);
	}

	private function parseResult( array $row_data ) {
		return [
			'id'          => $row_data['id'],
			'original'    => $row_data['original'],
			'context'     => $row_data['gettext_context'],
			'translation' => self::parseTranslation( $row_data ),
			'name'        => $row_data['name'],
		];
	}

	public static function parseTranslation( array $row_data ) {
		$value = null;

		if ( self::hasTranslatedValue( $row_data ) ) {
			$value = $row_data['translated'];
		} elseif ( ! empty( $row_data['mo_string'] ) ) {
			$value = $row_data['mo_string'];
		}

		return $value;
	}

	public static function hasTranslatedValue( array $row_data ) {
		if ( empty( $row_data['translated'] ) ) {
			return false;
		}

		return in_array( $row_data['status'], [ ICL_TM_COMPLETE, ICL_TM_NEEDS_UPDATE ] );
	}

	private function groupPluralFormsOfSameString( array $string ) {
		$groupKey = $this->getPluralGroupKey( $string );
		$pattern  = '/^(.+) \[plural ([0-9]+)\]$/';

		if ( preg_match( $pattern, $string['original'], $matches ) ) {
			$string['original'] = $matches[1];
			$string['index']    = $matches[2];
		} else {
			$string['index'] = null;
		}

		return [
			$string['original'] . self::KEY_JOIN . $string['context'] . self::KEY_JOIN . $groupKey => $string,
		];
	}

	private function getPluralGroupKey( array $string ) {
		$cannotBelongToPluralGroup = TranslateWpmlString::canTranslateWithMO( $string['original'], $string['name'] );

		if ( $cannotBelongToPluralGroup ) {
			return $string['name'];
		}

		return '';
	}

	private function buildStringEntity( Collection $strings, $key ) {
		$translations               = $strings->sortBy( 'index' )->pluck( 'translation' )->toArray();
		list( $original, $context ) = explode( self::KEY_JOIN, $key );
		$stringEntity               = new StringEntity( $original, $translations, $context );
		$stringEntity->set_name( $strings->first()['name'] );

		return $stringEntity;
	}
}
