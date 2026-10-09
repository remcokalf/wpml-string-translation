<?php

namespace WPML\ST\TranslationFile;

use function WPML\Container\make;
use WPML\ST\MO\Scan\UI\InstalledComponents;
use WPML\ST\MO\Scan\UI\Model;

class FilesToScanRepository {

	protected $fileDictionary;

	public function __construct(
		\WPML_ST_Translations_File_Dictionary $fileDictionary
	) {
		$this->fileDictionary = $fileDictionary;
	}

	public function getFilesToScanData() {
		$this->fileDictionary->clear_skipped();
		$filesToImport = InstalledComponents::filter( wpml_collect( $this->fileDictionary->get_not_imported_files() ) );

		$data = Model::provider( $filesToImport, 0, true, false )();

		return $data['files_to_scan'];
	}

	public function hasFilesToScan() {
		$data = $this->getFilesToScanData();

		return (
			count( $data['plugins'] ) > 0 ||
			count( $data['themes'] ) > 0 ||
			count( $data['other'] ) > 0
		);
	}

	public function getAllPluginIdsWithBackendTranslationFiles(): array {
		return $this->fileDictionary->getAllUniquePluginComponentIds( [ 'mo', 'php' ] );
	}
}
