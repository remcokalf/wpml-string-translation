<?php

namespace WPML\ST\MO\File;

trait makeDir {

	protected $filesystem;

	public function maybeCreateSubdir() {
		$subdir = $this->getSubdir();

		if ( $this->filesystem->is_dir( $subdir ) && $this->filesystem->is_writable( $subdir ) ) {
			return true;
		}

		$chmod = defined( 'FS_CHMOD_DIR' ) ? FS_CHMOD_DIR : 0755;
		return $this->filesystem->mkdir( $subdir, $chmod );
	}

}

