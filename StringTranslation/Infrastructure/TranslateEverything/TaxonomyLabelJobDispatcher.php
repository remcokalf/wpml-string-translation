<?php

namespace WPML\StringTranslation\Infrastructure\TranslateEverything;

use WPML\Core\Component\Translation\Application\String\Repository\StringBatchRepositoryInterface;
use WPML\FP\Cast;
use WPML\FP\Fns;
use WPML\FP\Lst;
use WPML\TM\AutomaticTranslation\Actions\Actions;

class TaxonomyLabelJobDispatcher {

	const BATCH_NAME_PREFIX = 'translate everything|taxonomy-label|';
	const ELEMENT_TYPE      = 'string';
	const ST_BATCH_TYPE     = 'st-batch';

	const LABEL_STRING_INDEXES = [ 0, 1 ];

	const SLUG_STRING_INDEX = 2;

	const SLUG_TRANSLATABLE_URL_MODES = [ 'translate', 'copy-encoded' ];

	private $taxonomyStrings;

	private $stringBatchRepository;

	private $wpdb;

	private $slugSettings;

	public function __construct(
		\WPML_ST_Taxonomy_Strings $taxonomyStrings,
		StringBatchRepositoryInterface $stringBatchRepository,
		?\wpdb $wpdb = null,
		?\WPML_ST_Tax_Slug_Translation_Settings $slugSettings = null
	) {
		$this->taxonomyStrings       = $taxonomyStrings;
		$this->stringBatchRepository = $stringBatchRepository;
		$this->slugSettings          = $slugSettings;

		if ( ! $wpdb ) {
			global $wpdb;
		}
		$this->wpdb = $wpdb;
	}

	public function collectLabelStringIds( array $taxonomies ): array {
		$ids    = [];
		$labels = [];

		foreach ( $taxonomies as $taxonomy ) {
			$strings = $this->taxonomyStrings->get_taxonomy_strings( $taxonomy );

			if ( is_array( $strings ) ) {
				$indexes = self::LABEL_STRING_INDEXES;

				if ( $this->isSlugTranslationEnabled( $taxonomy ) ) {
					$indexes[] = self::SLUG_STRING_INDEX;
				}

				foreach ( $indexes as $index ) {
					if ( ! isset( $strings[ $index ] ) || ! $strings[ $index ] ) {
						continue;
					}

					$stringId = (int) $strings[ $index ]->string_id();

					if ( $stringId ) {
						$ids[] = $stringId;
					}
				}
			}

			$names = $this->getLabelNames( $taxonomy );
			if ( $names ) {
				$labels[] = $names;
			}
		}

		$ids = array_merge( $ids, $this->getGettextContextLabelStringIds( $labels ) );

		return array_values( array_unique( array_filter( $ids ) ) );
	}

	public function shouldTranslateSlug( string $taxonomy ): bool {
		return $this->isSlugTranslationEnabled( $taxonomy );
	}

	private function isSlugTranslationEnabled( string $taxonomy ): bool {
		return in_array( $this->getPageUrlMode(), self::SLUG_TRANSLATABLE_URL_MODES, true );
	}

	public function enablePerTaxonomySlugSetting( array $taxonomies ) {
		foreach ( $taxonomies as $taxonomy ) {
			if ( ! $this->isSlugTranslationEnabled( $taxonomy ) ) {
				continue;
			}

			$settings = $this->getSlugSettings();

			if ( ! $settings->is_translated( $taxonomy ) ) {
				$settings->set_type( $taxonomy, true );
				$settings->save();
			}
		}
	}

	private function getSlugSettings() {
		if ( null === $this->slugSettings ) {
			$this->slugSettings = new \WPML_ST_Tax_Slug_Translation_Settings();
			$this->slugSettings->init();
		}

		return $this->slugSettings;
	}

	protected function getPageUrlMode(): string {
		if ( function_exists( 'wpml_get_setting_filter' ) ) {
			return (string) wpml_get_setting_filter( 'auto-generate', 'translated_document_page_url' );
		}

		return 'auto-generate';
	}

	private function getEncodedUrlLanguages(): array {
		$wpdb  = $this->wpdb;
		$codes = $wpdb->get_col( "SELECT code FROM {$wpdb->prefix}icl_languages WHERE encode_url = 1" );

		return array_map( 'strval', (array) $codes );
	}

	public function applyCopyEncodedSlugs( array $taxonomies, array $languages, string $sourceLanguage ) {
		if ( 'copy-encoded' !== $this->getPageUrlMode() ) {
			return;
		}

		$encodedLanguages = array_values( array_intersect( $languages, $this->getEncodedUrlLanguages() ) );
		if ( empty( $encodedLanguages ) ) {
			return;
		}

		foreach ( $taxonomies as $taxonomy ) {
			if ( ! $this->isSlugTranslationEnabled( $taxonomy ) ) {
				continue;
			}

			$strings = $this->taxonomyStrings->get_taxonomy_strings( $taxonomy );
			if ( ! is_array( $strings ) || empty( $strings[ self::SLUG_STRING_INDEX ] ) ) {
				continue;
			}

			$slug = $strings[ self::SLUG_STRING_INDEX ];
			foreach ( $encodedLanguages as $language ) {
				if ( $language === $sourceLanguage ) {
					continue;
				}

				$slug->set_translation( $language, $slug->get_value(), ICL_STRING_TRANSLATION_COMPLETE );
			}
		}
	}

	private function getLabelNames( string $taxonomy ): ?array {
		$taxonomyObject = get_taxonomy( $taxonomy );

		if ( ! $taxonomyObject || ! isset( $taxonomyObject->label ) || ! isset( $taxonomyObject->labels->singular_name ) ) {
			return null;
		}

		return [ $taxonomyObject->label, $taxonomyObject->labels->singular_name ];
	}

	private function getGettextContextLabelStringIds( array $labels ): array {
		if ( empty( $labels ) ) {
			return [];
		}

		$wpdb     = $this->wpdb;
		$general  = array_column( $labels, 0 );
		$singular = array_column( $labels, 1 );

		$found = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}icl_strings
				WHERE ( gettext_context = %s AND value IN ( " . implode( ',', array_fill( 0, count( $general ), '%s' ) ) . " ) )
					OR ( gettext_context = %s AND value IN ( " . implode( ',', array_fill( 0, count( $singular ), '%s' ) ) . ' ) )',
				array_merge(
					[ \WPML_ST_Taxonomy_Strings::CONTEXT_GENERAL ],
					$general,
					[ \WPML_ST_Taxonomy_Strings::CONTEXT_SINGULAR ],
					$singular
				)
			)
		);

		return array_map( 'intval', (array) $found );
	}

	public function getUntranslatedElements( array $stringIds, array $languages, int $queueSize, bool $includeCompleted = false ): array {
		if ( empty( $stringIds ) || empty( $languages ) ) {
			return [];
		}

		$languageSelect = implode(
			' UNION ALL ',
			array_fill( 0, count( $languages ), 'SELECT %s AS code' )
		);
		$languageArgs = array_values( $languages );

		$idsIn = implode( ',', array_map( 'intval', $stringIds ) );
		$wpdb  = $this->wpdb;

		if ( $includeCompleted ) {
			$rowset = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT strings.id, langs.code AS language_code
					FROM {$wpdb->prefix}icl_strings strings
					CROSS JOIN ( "
					. implode( ' UNION ALL ', array_fill( 0, count( $languages ), 'SELECT %s AS code' ) )
					. " ) AS langs
					LEFT JOIN {$wpdb->prefix}icl_string_translations translations
						ON strings.id = translations.string_id AND translations.language = langs.code
					WHERE strings.id IN ( " . esc_sql( $idsIn ) . ' )
						AND langs.code <> strings.language
					ORDER BY langs.code, strings.id ASC
					LIMIT %d',
					array_merge( $languageArgs, [ $queueSize ] )
				),
				ARRAY_N
			);
		} else {
			$rowset = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT strings.id, langs.code AS language_code
					FROM {$wpdb->prefix}icl_strings strings
					CROSS JOIN ( "
					. implode( ' UNION ALL ', array_fill( 0, count( $languages ), 'SELECT %s AS code' ) )
					. " ) AS langs
					LEFT JOIN {$wpdb->prefix}icl_string_translations translations
						ON strings.id = translations.string_id AND translations.language = langs.code
					WHERE strings.id IN ( " . esc_sql( $idsIn ) . ' )
						AND langs.code <> strings.language
						AND ( translations.status IS NULL OR translations.status = 0 )
					ORDER BY langs.code, strings.id ASC
					LIMIT %d',
					array_merge( $languageArgs, [ $queueSize ] )
				),
				ARRAY_N
			);
		}

		return Fns::map(
			function ( $row ) {
				return [ (int) $row[0], $row[1] ];
			},
			$rowset
		);
	}

	public function filterCreatable( array $elements, string $sourceLanguage ): array {
		if ( '' === $sourceLanguage ) {
			return [];
		}

		return array_values(
			array_filter(
				$elements,
				function ( $element ) use ( $sourceLanguage ) {
					return isset( $element[1] ) && '' !== (string) $element[1] && $element[1] !== $sourceLanguage;
				}
			)
		);
	}

	public function createJobs( Actions $actions, array $elements, string $sourceLanguage ): array {
		if ( '' === $sourceLanguage ) {
			return [];
		}

		$elements = $this->filterCreatable( $elements, $sourceLanguage );

		if ( ! $elements ) {
			return [];
		}

		$stringsGroupedByLanguages = \wpml_collect( $elements )
			->groupBy( 1 )
			->map( Lst::pluck( 0 ) )
			->map( Fns::map( Cast::toInt() ) )
			->toArray();

		$batchElements = [];
		foreach ( $stringsGroupedByLanguages as $languageCode => $strings ) {
			$batchId = $this->stringBatchRepository->create(
				self::BATCH_NAME_PREFIX . $languageCode,
				$strings,
				$sourceLanguage
			);

			$batchElements[] = [ $batchId, $languageCode ];
		}

		$jobs = $actions->createNewTranslationJobs( $sourceLanguage, $batchElements, self::ST_BATCH_TYPE );

		$jobsPerString = [];
		foreach ( $jobs as $job ) {
			if ( ! isset( $stringsGroupedByLanguages[ $job['lang'] ] ) ) {
				continue;
			}

			foreach ( $stringsGroupedByLanguages[ $job['lang'] ] as $string ) {
				$jobsPerString[] = [
					'elementId'   => $string,
					'lang'        => $job['lang'],
					'elementType' => self::ELEMENT_TYPE,
					'jobId'       => $job['jobId'],
				];
			}
		}

		return $jobsPerString;
	}
}
