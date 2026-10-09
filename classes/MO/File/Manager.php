<?php

namespace WPML\ST\MO\File;

use GlobIterator;
use WPML\Collect\Support\Collection;
use WPML\ST\DB\Mappers\StringsRetrieve as DbStringsRetrieve;
use WPML\ST\TranslationFile\Domains;
use WPML\ST\Storage\StoragePerLanguageInterface;
use WPML\ST\TranslationFile\StringsRetrieve;
use WPML\StringTranslation\Infrastructure\Core\Command\SaveFileCommand;
use WPML_Language_Records;

class Manager extends \WPML\ST\TranslationFile\Manager {

	public function __construct(
		StringsRetrieve $strings,
		Builder $builder,
		\WP_Filesystem_Base $filesystem,
		WPML_Language_Records $language_records,
		Domains $domains,
		?SaveFileCommand $save_file_command = null,
		?StoragePerLanguageInterface $index_storage = null
	) {
		parent::__construct( $strings, $builder, $filesystem, $language_records, $domains, $save_file_command, $index_storage );
	}

	protected function getFileExtension() {
		return 'mo';
	}

	public function isPartialFile() {
		return true;
	}

	protected function getDomains() {
		return $this->domains->getMODomains();
	}

	public function handles( $domain ) {
		if ( parent::handles( $domain ) ) {
			return true;
		}

		$isWordPressDomain = function ( $moDomain ) {
			return strtolower( DbStringsRetrieve::CONTEXT_WORDPRESS ) === strtolower( (string) $moDomain );
		};

		return DbStringsRetrieve::CONTEXT_DEFAULT === strtolower( (string) $domain )
			&& null !== $this->getDomains()->first( $isWordPressDomain );
	}

	public static function hasFiles() {
		return (bool) ( new GlobIterator( self::getSubdir() . '/*.mo' ) )->count();
	}
}
