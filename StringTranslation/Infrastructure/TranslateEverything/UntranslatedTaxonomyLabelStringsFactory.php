<?php

namespace WPML\StringTranslation\Infrastructure\TranslateEverything;

use WPML\Infrastructure\WordPress\Port\Persistence\DatabaseWrite;
use WPML\Legacy\Component\Translation\Application\String\Repository\StringBatchRepository;

class UntranslatedTaxonomyLabelStringsFactory {

	public function create(): UntranslatedTaxonomyLabelStrings {
		global $wpdb, $sitepress;

		$recordsFactory  = new \WPML_Slug_Translation_Records_Factory();
		$taxonomyStrings = new \WPML_ST_Taxonomy_Strings(
			$recordsFactory->createTaxRecords(),
			\WPML\Container\make( \WPML_ST_String_Factory::class )
		);

		$stringBatchRepository = new StringBatchRepository(
			new DatabaseWrite( $wpdb ),
			$sitepress
		);

		$slugSettings = new \WPML_ST_Tax_Slug_Translation_Settings();
		$slugSettings->init();

		$dispatcher = new TaxonomyLabelJobDispatcher(
			$taxonomyStrings,
			$stringBatchRepository,
			$wpdb,
			$slugSettings
		);

		return new UntranslatedTaxonomyLabelStrings( $dispatcher, $sitepress );
	}
}
