<?php

namespace WPML\StringTranslation\Infrastructure\TranslateEverything;

use WPML\Element\API\Languages;
use WPML\Setup\Option;
use WPML\ST\TranslationPauseScope;
use WPML\TM\API\ATE\CachedLanguageMappings;
use WPML\TM\API\ATE\LanguageMappings;
use WPML\TM\ATE\TranslateEverything\CreatableElementsInterface;
use WPML\TM\ATE\TranslateEverything\UntranslatedElementsInterface;
use WPML\TM\AutomaticTranslation\Actions\Actions;

class UntranslatedTaxonomyLabelStrings implements UntranslatedElementsInterface, CreatableElementsInterface {

	const TYPE       = 'taxonomy-label';
	const QUEUE_SIZE = 150;

	private $dispatcher;

	private $sitepress;

	public function __construct( TaxonomyLabelJobDispatcher $dispatcher, ?\SitePress $sitepress = null ) {
		$this->dispatcher = $dispatcher;

		if ( ! $sitepress ) {
			global $sitepress;
		}
		$this->sitepress = $sitepress;
	}

	public function getTypeWithLanguagesToProcess() {
		$target       = $this->getTargetLanguages( true );
		$notCompleted = array_diff( $target, $this->getCompleted() );

		$withUntranslated = $this->getLanguagesWithUntranslatedLabels( $target );

		return [ self::TYPE, array_values( array_unique( array_merge( $notCompleted, $withUntranslated ) ) ) ];
	}

	public function getElementsToProcess( $languages, $type, $queueSize ) {
		$taxonomies = $this->getTranslatableTaxonomies();
		$stringIds  = $this->dispatcher->collectLabelStringIds( $taxonomies );

		$this->dispatcher->applyCopyEncodedSlugs( $taxonomies, $languages, $this->getSourceLanguage() );

		$this->dispatcher->enablePerTaxonomySlugSetting( $taxonomies );

		return $this->dispatcher->getUntranslatedElements( $stringIds, $languages, $queueSize );
	}

	public function createTranslationJobs( Actions $actions, array $elements, $type ) {
		return $this->dispatcher->createJobs( $actions, $elements, $this->getSourceLanguage() );
	}

	public function filterCreatableElements( array $elements ): array {
		return $this->dispatcher->filterCreatable( $elements, $this->getSourceLanguage() );
	}

	public function isEverythingProcessed( $cached = false ) {
		$target = $this->getTargetLanguages( $cached );

		return count( array_diff( $target, $this->getCompleted() ) ) === 0
			&& empty( $this->getLanguagesWithUntranslatedLabels( $target ) );
	}

	private function getLanguagesWithUntranslatedLabels( array $languages ): array {
		if ( empty( $languages ) ) {
			return [];
		}

		$stringIds = $this->dispatcher->collectLabelStringIds( $this->getTranslatableTaxonomies() );
		if ( empty( $stringIds ) ) {
			return [];
		}

		$elements = $this->dispatcher->getUntranslatedElements( $stringIds, $languages, self::QUEUE_SIZE );

		return array_values( array_unique( array_map(
			static function ( $element ) {
				return $element[1];
			},
			$elements
		) ) );
	}

	public function getQueueSize(): int {
		return self::QUEUE_SIZE;
	}

	public function getEligibleLanguageCodes( bool $cached = false ): array {
		return $this->getTargetLanguages( $cached );
	}

	public function markTypeAsCompleted( string $type ) {
		$this->setCompleted( $this->getCompletableLanguageCodes() );
	}

	public function markEverythingAsCompleted() {
		$this->setCompleted( $this->getCompletableLanguageCodes() );
	}

	private function getCompletableLanguageCodes(): array {
		$sourceLanguage = $this->getSourceLanguage();

		return array_values(
			array_filter(
				array_map( 'strval', (array) Languages::getSecondaryCodes() ),
				function ( $languageCode ) use ( $sourceLanguage ) {
					return '' !== $languageCode && $languageCode !== $sourceLanguage;
				}
			)
		);
	}

	public function markEverythingAsUncompleted() {
		$this->setCompleted( [] );
	}

	public function markLanguagesAsCompleted( array $languages ) {
		$this->setCompleted( array_values( array_unique( array_merge( $this->getCompleted(), $languages ) ) ) );
	}

	public function markLanguagesAsUncompleted( array $languages ) {
		$this->setCompleted( array_values( array_diff( $this->getCompleted(), $languages ) ) );
	}

	private function getTargetLanguages( bool $cached = false ): array {
		$sourceLanguage = $this->getSourceLanguage();

		if ( '' === $sourceLanguage ) {
			return [];
		}

		$languageMapper  = $cached ? CachedLanguageMappings::class : LanguageMappings::class;
		$targetLanguages = $languageMapper::geCodesEligibleForAutomaticTranslations();

		return TranslationPauseScope::translatable(
			array_values(
				array_filter(
					$targetLanguages,
					function ( $languageCode ) use ( $sourceLanguage ) {
						return $languageCode !== $sourceLanguage;
					}
				)
			)
		);
	}

	private function getTranslatableTaxonomies(): array {
		$taxonomies = [];

		foreach ( get_taxonomies( [], 'names' ) as $taxonomy ) {
			if ( $this->sitepress && $this->sitepress->is_translated_taxonomy( $taxonomy ) ) {
				$taxonomies[] = $taxonomy;
			}
		}

		return $taxonomies;
	}

	private function getSourceLanguage(): string {
		return Languages::getDefaultCode();
	}

	private function getCompleted(): array {
		return Option::getTranslateEverythingCompletedTaxonomyLabels();
	}

	private function setCompleted( array $completed ) {
		Option::setTranslateEverythingCompletedTaxonomyLabels( $completed );
	}
}
