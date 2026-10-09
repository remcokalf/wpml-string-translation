<?php

namespace WPML\ST\TranslationFile\Sync;

use WPML\ST\TranslationFile\Manager;
use WPML_ST_Translations_File_Locale;

class FileSync {

	private $manager;

	private $translationUpdates;

	private $fileLocale;

	public function __construct(
		Manager $manager,
		TranslationUpdates $translationUpdates,
		WPML_ST_Translations_File_Locale $FileLocale
	) {
		$this->manager            = $manager;
		$this->translationUpdates = $translationUpdates;
		$this->fileLocale         = $FileLocale;
	}

	public function sync( $filePath, $domain ) {
		if ( ! $filePath ) {
			return;
		}

		$locale        = $this->fileLocale->get( $filePath, $domain );
		$filePath      = $this->getCustomFilePath( $filePath, $domain, $locale );
		$lastDbUpdate  = $this->translationUpdates->getTimestamp( $domain, $locale );
		$fileTimestamp = file_exists( $filePath ) ? filemtime( $filePath ) : 0;

		if ( 0 === $lastDbUpdate ) {
			if ( $fileTimestamp ) {
				$this->manager->remove( $domain, $locale );
			}
		} elseif ( $fileTimestamp < $lastDbUpdate ) {
			$this->manager->add( $domain, $locale );
		}
	}



	private function getCustomFilePath( $filePath, $domain, $locale ) {
		if ( self::isWpmlCustomFile( $filePath ) ) {
			return $filePath;
		}

		return $this->manager->getFilepath( $domain, $locale );
	}

	private static function isWpmlCustomFile( $file ) {
		return 0 === strpos( $file, WP_LANG_DIR . '/' . Manager::SUB_DIRECTORY );
	}
}
