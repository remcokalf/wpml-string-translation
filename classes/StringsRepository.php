<?php

namespace WPML\ST;

use WPML\FP\Fns;

class StringsRepository {
	private $sitepress;

	private $wpdb;

	public function __construct( \SitePress $sitepress, \wpdb $wpdb ) {
		$this->sitepress = $sitepress;
		$this->wpdb      = $wpdb;
	}

	private function execGetCountInDomains( $domains = [], $langs = [], $notPriorities = [] ) {
		if ( ! $domains ) {
			return 0;
		}
		$wpdb = $this->wpdb;

		if ( $langs ) {
			return (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT count(id) FROM {$wpdb->prefix}icl_strings WHERE context IN ("
					. implode( ', ', array_fill( 0, count( $domains ), '%s' ) )
					. ') AND language IN (' . implode( ', ', array_fill( 0, count( $langs ), '%s' ) ) . ')',
					...array_merge( array_values( $domains ), array_values( $langs ) )
				)
			);
		}

		if ( $notPriorities ) {
			return (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT count(id) FROM {$wpdb->prefix}icl_strings WHERE context IN ("
					. implode( ', ', array_fill( 0, count( $domains ), '%s' ) )
					. ') AND translation_priority NOT IN ('
					. implode( ', ', array_fill( 0, count( $notPriorities ), '%s' ) ) . ')',
					...array_merge( array_values( $domains ), array_values( $notPriorities ) )
				)
			);
		}

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT count(id) FROM {$wpdb->prefix}icl_strings WHERE context IN ("
				. implode( ', ', array_fill( 0, count( $domains ), '%s' ) ) . ')',
				...array_values( $domains )
			)
		);
	}

	public function getCountInDomains( $domains = [] ) {
		return $this->execGetCountInDomains( $domains );
	}

	public function getCountInDomainsByLangs( $domains = [], $langs = [] ) {
		if ( ! $langs ) {
			return 0;
		}

		return $this->execGetCountInDomains( $domains, $langs );
	}

	public function getCountInDomainsByNotPriorities( $domains = [], $notPriorities = [] ) {
		return $this->execGetCountInDomains( $domains, [], $notPriorities );
	}

	private function execGetFromDomains( $domains = [], $limit = 20, $langs = [], $notPriorities = [] ) {
		if ( ! $domains ) {
			return [];
		}
		$wpdb = $this->wpdb;

		if ( $langs ) {
			return $wpdb->get_col(
				$wpdb->prepare(
					"SELECT id FROM {$wpdb->prefix}icl_strings WHERE context IN ("
					. implode( ', ', array_fill( 0, count( $domains ), '%s' ) )
					. ') AND language IN (' . implode( ', ', array_fill( 0, count( $langs ), '%s' ) )
					. ') LIMIT 0, %d',
					...array_merge( array_values( $domains ), array_values( $langs ), [ $limit ] )
				)
			);
		}

		if ( $notPriorities ) {
			return $wpdb->get_col(
				$wpdb->prepare(
					"SELECT id FROM {$wpdb->prefix}icl_strings WHERE context IN ("
					. implode( ', ', array_fill( 0, count( $domains ), '%s' ) )
					. ') AND translation_priority NOT IN ('
					. implode( ', ', array_fill( 0, count( $notPriorities ), '%s' ) )
					. ') LIMIT 0, %d',
					...array_merge( array_values( $domains ), array_values( $notPriorities ), [ $limit ] )
				)
			);
		}

		return $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}icl_strings WHERE context IN ("
				. implode( ', ', array_fill( 0, count( $domains ), '%s' ) ) . ') LIMIT 0, %d',
				...array_merge( array_values( $domains ), [ $limit ] )
			)
		);
	}

	public function countExistingIds( array $ids ) {
		return $this->countIds( $ids, '', null );
	}

	public function countIdsNotInLanguage( array $ids, $language ) {
		return $this->countIds( $ids, 'language', $language );
	}

	public function countIdsWithoutPriority( array $ids, $priority ) {
		return $this->countIds( $ids, 'priority', $priority );
	}

	private function countIds( array $ids, $differentFrom, $value ) {
		if ( ! $ids ) {
			return 0;
		}
		$wpdb = $this->wpdb;
		$ids  = array_map( 'intval', array_values( $ids ) );

		if ( 'language' === $differentFrom ) {
			return (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(id) FROM {$wpdb->prefix}icl_strings WHERE id IN ("
					. implode( ', ', array_fill( 0, count( $ids ), '%d' ) ) . ') AND language <> %s',
					...array_merge( $ids, [ (string) $value ] )
				)
			);
		}

		if ( 'priority' === $differentFrom ) {
			return (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(id) FROM {$wpdb->prefix}icl_strings WHERE id IN ("
					. implode( ', ', array_fill( 0, count( $ids ), '%d' ) ) . ') AND translation_priority <> %s',
					...array_merge( $ids, [ (string) $value ] )
				)
			);
		}

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(id) FROM {$wpdb->prefix}icl_strings WHERE id IN ("
				. implode( ', ', array_fill( 0, count( $ids ), '%d' ) ) . ')',
				...$ids
			)
		);
	}

	public function getFromDomains( $domains = [], $limit = 20 ) {
		return $this->execGetFromDomains( $domains, $limit );
	}

	public function getStringIdFromDomainsByLangs( $domains = [], $langs = [], $limit = 20 ) {
		if ( ! $langs ) {
			return [];
		}

		return $this->execGetFromDomains( $domains, $limit, $langs );
	}

	public function getStringIdsFromDomainsWithExcludedPriorities( $domains = [], $notPriorities = [], $limit = 20 ) {
		return $this->execGetFromDomains( $domains, $limit, [], $notPriorities );
	}

	public function getLanguagesUsedInDomains( $domains = [], $ignoreLangs = [] ) {
		$wpdb = $this->wpdb;

		if ( ! $domains ) {
			return [];
		}

		$allLanguages = array_keys( $this->sitepress->get_languages( $this->sitepress->get_admin_language() ) );
		$allLanguages = array_values(
			Fns::filter(
				function( $language ) use ( $ignoreLangs ) {
					return ! in_array( $language, $ignoreLangs );
				},
				$allLanguages
			)
		);
		if ( ! $allLanguages ) {
			return [];
		}

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT(language) FROM {$wpdb->prefix}icl_strings s WHERE context IN ("
				. implode( ', ', array_fill( 0, count( $domains ), '%s' ) )
				. ') AND language IN (' . implode( ', ', array_fill( 0, count( $allLanguages ), '%s' ) ) . ')',
				...array_merge( array_values( $domains ), $allLanguages )
			),
			ARRAY_A
		);

		return Fns::map(
			function( $data ) {
				return $data['language'];
			},
			$results
		);
	}
}
