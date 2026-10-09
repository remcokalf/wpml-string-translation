<?php

namespace WPML\StringTranslation\Infrastructure\StringPackage\Query\ManyLanguagesStrategy;

use WPML\StringTranslation\Application\Setting\Repository\SettingsRepositoryInterface as SettingsRepository;
use WPML\StringTranslation\Application\StringPackage\Query\Criteria\SearchPopulatedKindsCriteria;
use WPML\StringTranslation\Application\StringPackage\Query\SearchPopulatedKindsQueryBuilderInterface;
use WPML\StringTranslation\Infrastructure\StringPackage\Query\QueryBuilderTrait;

class SearchPopulatedKindsQueryBuilder implements SearchPopulatedKindsQueryBuilderInterface {
	use QueryBuilderTrait;
	use TranslationStatusQueryBuilderTrait;

	private $wpdb;

	private $sitepress;

	private $settingsRepository;

	public function __construct(
		$wpdb,
		$sitepress,
		SettingsRepository $settingsRepository
	) {
		$this->wpdb               = $wpdb;
		$this->sitepress          = $sitepress;
		$this->settingsRepository = $settingsRepository;
	}

	public function build( SearchPopulatedKindsCriteria $criteria, $stringPackageId ): string {
		$sourceLanguage = $this->sqlStringLiteral( $this->getSourceLanguageCode( $criteria ) );
		$languageCodes = $this->getTargetLanguageCodes( $criteria );
		$stringPackageId = $this->sqlStringLiteral( $stringPackageId );

		$sql = "
			SELECT sp.kind_slug
			FROM {$this->wpdb->prefix}icl_string_packages sp
			INNER JOIN {$this->wpdb->prefix}icl_translations source_t
			  ON source_t.element_id = sp.ID
			  AND source_t.element_type = CONCAT('package_', sp.kind_slug)
			  AND source_t.language_code = {$sourceLanguage}
			LEFT JOIN {$this->wpdb->prefix}icl_translations target_t
			  ON target_t.trid = source_t.trid
			  AND " . $this->buildLanguageInCondition( 'target_t.language_code', $languageCodes ) . "
			LEFT JOIN {$this->wpdb->prefix}icl_translation_status target_ts
			  ON target_ts.translation_id = target_t.translation_id
			WHERE
				sp.kind_slug = {$stringPackageId}
				{$this->buildTranslationStatusConditionWrapper( $criteria, $languageCodes )}
			LIMIT 0,1;
        ";

		return $sql;
	}

	private function buildTranslationStatusConditionWrapper(
		SearchPopulatedKindsCriteria $criteria,
		array $targetLanguageCodes
	) : string {
		return 'AND ' . $this->buildTranslationStatusCondition( $criteria, $targetLanguageCodes );
	}
}
