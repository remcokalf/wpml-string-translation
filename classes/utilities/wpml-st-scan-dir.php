<?php

class WPML_ST_Scan_Dir {
	const PLACEHOLDERS_ROOT = '<root>';

	public function scan( $folder, array $extensions = array(), $single_file = false, $ignore_folders = array() ) {

		$files          = array();
		$scanned_files  = array();
		$ignore_folders = array_map(
			function( $ignore_folder ) use ( $folder ) {
				return str_replace( self::PLACEHOLDERS_ROOT, $folder, $ignore_folder );
			},
			$ignore_folders
		);

		if ( is_dir( $folder ) ) {
			$directory_iterator = new RecursiveDirectoryIterator( $folder, FilesystemIterator::SKIP_DOTS );

			if ( $ignore_folders ) {
				$directory_iterator = new RecursiveCallbackFilterIterator(
					$directory_iterator,
					function( $file ) use ( $ignore_folders ) {
						return ! $this->is_ignored_path( $file->getPathname(), $ignore_folders );
					}
				);
			}

			$scanned_files = new RecursiveIteratorIterator( $directory_iterator );
		} elseif ( $single_file ) {
			$scanned_files = array( new SplFileInfo( $folder ) );
		}

		foreach ( $scanned_files as $file ) {
			if (
				in_array( $file->getExtension(), $extensions, true )
				&& ! $this->is_ignored_path( $file->getPathname(), $ignore_folders )
			) {
				$files[] = $file->getPathname();
			}
		}

		return $files;
	}

	private function is_ignored_path( $path, array $ignore_folders ) {
		foreach ( $ignore_folders as $ignore_folder ) {
			if ( false !== strpos( $ignore_folder, '*' ) ) {
				if (
					fnmatch( $ignore_folder, $path )
					|| fnmatch( rtrim( $ignore_folder, '/\\' ) . '/*', $path )
				) {
					return true;
				}

				continue;
			}

			if ( false !== strpos( $path, $ignore_folder ) ) {
				return true;
			}
		}

		return false;
	}
}
