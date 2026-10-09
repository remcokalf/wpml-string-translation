<?php

namespace WPML\ST\TranslationFile;

use wpdb;
use WPML\Collect\Support\Collection;
use WPML\FP\Fns;
use WPML\FP\Lst;
use WPML\FP\Obj;
use WPML_Locale;

class DomainsLocalesMapper {

	const ALIAS_STRINGS             = 's';
	const ALIAS_STRING_TRANSLATIONS = 'st';

	private $wpdb;

	private $locale;

	public function __construct( wpdb $wpdb, WPML_Locale $locale ) {
		$this->wpdb   = $wpdb;
		$this->locale = $locale;
	}

	public function get_from_translation_ids( array $string_translation_ids ) {
		return $this->map_rows( $this->rows_by_translation_ids( $string_translation_ids ) );
	}

	public function get_from_string_ids( array $string_ids ) {
		return $this->map_rows( $this->rows_by_string_ids( $string_ids ) );
	}

	public function get_from_domain( callable $getActiveLanguages, $domain ) {
		$createEntity = function ( $locale ) use ( $domain ) {
			return (object) [
				'domain' => $domain,
				'locale' => $locale,
			];
		};

		$defaultLocaleList = Lst::pluck( 'default_locale', $getActiveLanguages() );
		return Fns::map( $createEntity, Obj::values( $defaultLocaleList ) );
	}

	private function rows_by_translation_ids( array $ids ) {
		$wpdb = $this->wpdb;
		$ids  = $this->numeric_ids( $ids );

		if ( ! $ids ) {
			return [];
		}

		return (array) $this->wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT
					s.context AS domain,
					s.name AS name,
					s.value AS value,
					st.language
				FROM {$wpdb->prefix}icl_string_translations AS st
				JOIN {$wpdb->prefix}icl_strings AS s ON s.id = st.string_id
				WHERE st.id IN(" . implode( ', ', array_fill( 0, count( $ids ), '%d' ) ) . ')',
				$ids
			)
		);
	}

	private function rows_by_string_ids( array $ids ) {
		$wpdb = $this->wpdb;
		$ids  = $this->numeric_ids( $ids );

		if ( ! $ids ) {
			return [];
		}

		return (array) $this->wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT
					s.context AS domain,
					s.name AS name,
					s.value AS value,
					st.language
				FROM {$wpdb->prefix}icl_string_translations AS st
				JOIN {$wpdb->prefix}icl_strings AS s ON s.id = st.string_id
				WHERE s.id IN(" . implode( ', ', array_fill( 0, count( $ids ), '%d' ) ) . ')',
				$ids
			)
		);
	}

	private function numeric_ids( array $ids ) {
		return array_values( array_filter( array_map( 'intval', $ids ) ) );
	}

	private function map_rows( array $results ) {
		return wpml_collect( $results )->flatMap(
			function( $row ) {
				$locale   = $this->locale->get_locale( $row->language );
				$entities = [
					(object) [
						'domain' => $row->domain,
						'locale' => $locale,
					],
				];

				if ( 'wordpress' === strtolower( (string) $row->domain ) && md5( (string) $row->value ) === $row->name ) {
					$entities[] = (object) [
						'domain' => 'default',
						'locale' => $locale,
					];
				}

				return $entities;
			}
		)->filter(
			function( $entry ) {
				return false !== $entry->locale;
			}
		)->unique( [ self::class, 'entityKey' ] )->values();
	}

	public static function entityKey( $entity ) {
		return strtolower( (string) $entity->domain ) . "\0" . $entity->locale;
	}
}
