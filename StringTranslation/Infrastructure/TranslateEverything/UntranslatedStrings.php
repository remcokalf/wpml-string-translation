<?php

namespace WPML\StringTranslation\Infrastructure\TranslateEverything;

use WPML\API\PostTypes;
use WPML\Core\Component\Translation\Application\String\Repository\StringBatchRepositoryInterface;
use WPML\Core\SharedKernel\Component\Language\Domain\LanguageCode;
use WPML\Element\API\Languages;
use WPML\FP\Cast;
use WPML\FP\Fns;
use WPML\FP\Lst;
use WPML\FP\Obj;
use WPML\Setup\Option;
use WPML\ST\TranslationPauseScope;
use WPML\TM\API\ATE\CachedLanguageMappings;
use WPML\TM\API\ATE\LanguageMappings;
use WPML\TM\ATE\TranslateEverything\UntranslatedElementsInterface;
use WPML\TM\AutomaticTranslation\Actions\Actions;

class UntranslatedStrings implements UntranslatedElementsInterface {

	const ENGLISH_SOURCE_LANGUAGE = LanguageCode::ENGLISH;

	private $wpdb;

	private $stringBatchRepository;

	public function __construct( StringBatchRepositoryInterface $stringBatchRepository, ?\wpdb $wpdb = null ) {
		$this->stringBatchRepository = $stringBatchRepository;

		if ( ! $wpdb ) {
			global $wpdb;
		}
		$this->wpdb = $wpdb;
	}

	public function getTypeWithLanguagesToProcess() {
		$completed             = $this->getCompleted();
		$notCompletedLanguages = array_diff( $this->getEligibleLanguageCodes( true ), $completed );

		return [ 'string', $notCompletedLanguages ];
	}

	public function getElementsToProcess( $languages, $type, $queueSize ) {
		$languageSelect    = implode(
			' UNION ALL ',
			array_fill( 0, count( $languages ), 'SELECT %s AS code' )
		);
		$languageCrossJoin = "
			CROSS JOIN (
				$languageSelect	
			) as langs
		";

		$sql = "
			SELECT strings.id, langs.code AS language_code
			FROM {$this->wpdb->prefix}icl_strings strings
			{$languageCrossJoin}
			LEFT JOIN {$this->wpdb->prefix}icl_string_translations translations
				ON strings.id = translations.string_id AND translations.language = langs.code
			WHERE (
					(
						strings.string_type = 1
						AND EXISTS (
							SELECT 1
							FROM {$this->wpdb->prefix}icl_string_positions positions
							WHERE positions.string_id = strings.id
								AND positions.kind = %d
						)
					)
					-- The site-identity strings (site title + tagline) are registered
					-- programmatically by WPML core itself -- see
					-- WPML_String_Translation::initialize_wp_and_widget_strings()
					-- (inc/wpml-string-translation.class.php), which calls
					-- icl_register_string( 'WP', 'Blog Title' | 'Tagline', ... ). Being
					-- programmatic they are string_type 0 and carry no frontend-kind
					-- position row, so they failed BOTH filters above and never entered
					-- TEA's string scope even though they are the most visible strings on
					-- the site (wpmldev-7226). Admitted here as an exactly-named set, not
					-- as every type-0 string, which would sweep the whole admin-text
					-- corpus into automatic translation and billing.
					OR (
						strings.context = %s
						AND strings.name IN ( %s, %s )
					)
				)
				AND ( translations.status IS NULL OR translations.status = 0 )
				-- wpmldev-7665: the line above reads the ST status MIRROR, which
				-- is written after ATE has been called and billed, by a deferred
				-- closure on the far side of a hook chain that can fail in
				-- between. A mirror reading 0 or NULL is therefore not proof
				-- the string is idle -- in every reproducing run the mirror was the
				-- only thing that was wrong while the job tables were correct,
				-- so strings that had already been sent and paid for were picked
				-- again, re-batched byte-identically and re-billed.
				--
				-- Ask the job tables instead: a string carrying an ACTIVE
				-- st-batch job for this language is in flight, full stop. Same
				-- join shape JobLog's own in-flight precheck uses.
				AND NOT EXISTS (
					SELECT 1
					FROM {$this->wpdb->prefix}icl_string_batches active_batch
					INNER JOIN {$this->wpdb->prefix}icl_translations active_original
						ON active_original.element_id = active_batch.batch_id
						AND active_original.element_type = 'st-batch_strings'
						AND active_original.source_language_code IS NULL
					INNER JOIN {$this->wpdb->prefix}icl_translations active_target
						ON active_target.trid = active_original.trid
						AND active_target.source_language_code IS NOT NULL
					INNER JOIN {$this->wpdb->prefix}icl_translation_status active_status
						ON active_status.translation_id = active_target.translation_id
					-- Both correlations live in the WHERE on purpose: MySQL does
					-- not resolve an outer reference inside a subquery ON clause.
					WHERE active_batch.string_id = strings.id
						AND active_target.language_code = langs.code
						AND active_status.status IN ( %d, %d )
				)
				AND strings.language = %s
				-- Never target a string own source language. TEA appends the site
				-- default language to the target list (wpmldev-6406), so on an
				-- English-default site en appears as both source and target; without
				-- this guard each English string would be batched to translate into
				-- English, producing an empty-source ATE job (the Missing language
				-- mapping sync error, wpmldev-7222). Keyed on the string actual
				-- source, so a non-English string is still translated into English.
				AND langs.code != strings.language
			ORDER BY langs.code, strings.id ASC
			LIMIT %d
		";

		$sql = $this->wpdb->prepare(
			$sql,
			array_merge(
				array_values( $languages ),
				[
					ICL_STRING_TRANSLATION_STRING_TRACKING_TYPE_FRONTEND,
					\WPML_ST_Blog_Name_And_Description_Hooks::STRING_DOMAIN,
					\WPML_ST_Blog_Name_And_Description_Hooks::STRING_NAME_BLOGNAME,
					\WPML_ST_Blog_Name_And_Description_Hooks::STRING_NAME_BLOGDESCRIPTION,
					ICL_TM_WAITING_FOR_TRANSLATOR,
					ICL_TM_IN_PROGRESS,
					$this->getSourceLanguage(),
					$queueSize,
				]
			)
		);

		$rowset = $this->wpdb->get_results( $sql, ARRAY_N );

		return Fns::map(
			function ( $row ) {
				return [ (int) $row[0], $row[1] ];
			},
			$rowset
		);
	}

	public function createTranslationJobs( Actions $actions, array $elements, $type ) {
		$stringsGroupedByLanguages = \wpml_collect( $elements )
			->groupBy( 1 )
			->map( Lst::pluck( 0 ) )
			->map( Fns::map( Cast::toInt() ) )
			->toArray();

		$sourceLanguage = $this->getSourceLanguage();

		$batchElements = [];
		foreach ( $stringsGroupedByLanguages as $languageCode => $strings ) {
			$alreadyInFlight = $this->getStringIdsWithActiveJob( $strings, $languageCode );

			if ( $alreadyInFlight ) {
				$this->reportRefusedDuplicateJob( $alreadyInFlight, $languageCode );

				$strings = array_values( array_diff( $strings, $alreadyInFlight ) );

				$stringsGroupedByLanguages[ $languageCode ] = $strings;
			}

			if ( ! $strings ) {
				continue;
			}

			$batchId = $this->stringBatchRepository->create(
				'translate everything|string|' . $languageCode,
				$strings,
				$sourceLanguage
			);

			$this->markStringsAsWaiting( $strings, $languageCode );

			$batchElements[] = [ $batchId, $languageCode ];
		}

		if ( ! $batchElements ) {
			return [];
		}

		$jobs = $actions->createNewTranslationJobs( $sourceLanguage, $batchElements, 'st-batch' );

		$jobsPerString = [];
		foreach ( $jobs as $job ) {
			$stringsInJobLanguages = $stringsGroupedByLanguages[ $job['lang'] ];

			foreach ( $stringsInJobLanguages as $string ) {
				$jobsPerString[] = [
					'elementId'   => $string,
					'lang'        => $job['lang'],
					'elementType' => 'string',
					'jobId'       => $job['jobId'],
				];
			}
		}

		return $jobsPerString;
	}

	private function getStringIdsWithActiveJob( array $stringIds, string $languageCode ): array {
		if ( ! $stringIds ) {
			return [];
		}

		$placeholders = implode( ', ', array_fill( 0, count( $stringIds ), '%d' ) );

		$sql = "
			SELECT DISTINCT active_batch.string_id
			FROM {$this->wpdb->prefix}icl_string_batches active_batch
			INNER JOIN {$this->wpdb->prefix}icl_translations active_original
				ON active_original.element_id = active_batch.batch_id
				AND active_original.element_type = 'st-batch_strings'
				AND active_original.source_language_code IS NULL
			INNER JOIN {$this->wpdb->prefix}icl_translations active_target
				ON active_target.trid = active_original.trid
				AND active_target.language_code = %s
				AND active_target.source_language_code IS NOT NULL
			INNER JOIN {$this->wpdb->prefix}icl_translation_status active_status
				ON active_status.translation_id = active_target.translation_id
			WHERE active_batch.string_id IN ( {$placeholders} )
				AND active_status.status IN ( %d, %d )
		";

		$rows = $this->wpdb->get_col(
			$this->wpdb->prepare(
				$sql,
				array_merge(
					[ $languageCode ],
					array_map( 'intval', array_values( $stringIds ) ),
					[ ICL_TM_WAITING_FOR_TRANSLATOR, ICL_TM_IN_PROGRESS ]
				)
			)
		);

		return array_map( 'intval', (array) $rows );
	}

	private function reportRefusedDuplicateJob( array $stringIds, string $languageCode ) {
		if ( class_exists( \WPML\TM\Jobs\JobLog::class ) ) {
			\WPML\TM\Jobs\JobLog::addError(
				'tea_duplicate_string_job_refused',
				[
					'language'   => $languageCode,
					'string_ids' => array_values( $stringIds ),
					'count'      => count( $stringIds ),
				]
			);
		}

		do_action( 'wpml_st_batch_duplicate_job_refused', array_values( $stringIds ), $languageCode );
	}

	private function markStringsAsWaiting( array $stringIds, string $languageCode ) {
		foreach ( $stringIds as $stringId ) {
			icl_add_string_translation( (int) $stringId, $languageCode, null, ICL_TM_WAITING_FOR_TRANSLATOR );
		}
	}

	public function isEverythingProcessed( $cached = false ) {
		$completed = $this->getCompleted();

		return count( array_diff( $this->getEligibleLanguageCodes( $cached ), $completed ) ) === 0;
	}

	public function getQueueSize(): int {
		return 150;
	}

	public function getEligibleLanguageCodes( bool $cached = false ): array {
		$languageMapper = $cached ? CachedLanguageMappings::class : LanguageMappings::class;

		$targetLanguages = $languageMapper::geCodesEligibleForAutomaticTranslations();

		$targetLanguages = $this->maybeAppendDefaultLanguage( $languageMapper, $targetLanguages );

		$targetLanguages = $this->removeEnglishFromTargetLanguages( $targetLanguages );

		return TranslationPauseScope::translatable( array_values( $targetLanguages ) );
	}

	private function getTargetLanguages(): array {
		$targetLanguages = Languages::getSecondaryCodes();

		if ( Languages::getDefaultCode() !== $this->getSourceLanguage() ) {
			$primary         = [ Languages::getDefaultCode() ];
			$targetLanguages = array_merge( $targetLanguages, $primary );
		}

		$targetLanguages = $this->removeEnglishFromTargetLanguages( $targetLanguages );

		return $targetLanguages;
	}

	public function markTypeAsCompleted( string $type ) {
		$this->setCompleted( $this->getTargetLanguages() );
	}

	public function markEverythingAsCompleted() {
		$this->setCompleted( $this->getTargetLanguages() );
	}

	public function markEverythingAsUncompleted() {
		$this->setCompleted( [] );
	}

	public function markLanguagesAsCompleted( array $languages ) {
		$completed = $this->getCompleted();
		$completed = array_merge( $completed, $languages );
		$this->setCompleted( $completed );
	}

	public function markLanguagesAsUncompleted( array $languages ) {
		$completed = $this->getCompleted();
		$completed = array_diff( $completed, $languages );
		$this->setCompleted( $completed );
	}

	private function getCompleted() {
		return Option::getTranslateEverythingCompletedStrings();
	}

	private function setCompleted( array $completed ) {
		Option::setTranslateEverythingCompletedStrings( $completed );
	}

	private function removeEnglishFromTargetLanguages( array $targetLanguages ): array {
		$sourceLanguage  = $this->getSourceLanguage();
		$targetLanguages = array_filter(
			$targetLanguages,
			function ( $languageCode ) use ( $sourceLanguage ) {
				return $sourceLanguage !== $languageCode;
			}
		);

		return $targetLanguages;
	}

	private function getSourceLanguage(): string {
		return EnglishSourceLanguage::resolveForSite();
	}

	private function maybeAppendDefaultLanguage( string $languageMapper, array $targetLanguages ): array {
		if ( Languages::getDefaultCode() !== $this->getSourceLanguage() && $languageMapper::doesDefaultLanguageSupportAutomaticTranslations() ) {
			$targetLanguages[] = Languages::getDefaultCode();
		}

		return $targetLanguages;
	}
}
