<?php

namespace WP_CLI;

use DirectoryIterator;
use Exception;
use PharData;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use WP_CLI;
use ZipArchive;

class Extractor {

	public static function extract( $tarball_or_zip, $dest ) {
		if ( preg_match( '/\.zip$/', $tarball_or_zip ) ) {
			return self::extract_zip( $tarball_or_zip, $dest );
		}

		if ( preg_match( '/\.tar\.gz$/', $tarball_or_zip ) ) {
			return self::extract_tarball( $tarball_or_zip, $dest );
		}

		throw new Exception( "Extraction only supported for '.zip' and '.tar.gz' file types." );
	}

	private static function extract_zip( $zipfile, $dest ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			throw new Exception( 'Extracting a zip file requires ZipArchive.' );
		}

		if ( ! self::ensure_dir_exists( $dest ) ) {
			throw new Exception( "Could not create folder '{$dest}'." );
		}

		if ( ! file_exists( $zipfile )
			|| ! is_readable( $zipfile )
			|| filesize( $zipfile ) <= 0 ) {
			throw new Exception( "Invalid zip file '{$zipfile}'." );
		}

		$zip = new ZipArchive();
		$res = $zip->open( $zipfile );

		if ( true === $res ) {
			$name    = Utils\basename( $zipfile );
			$tempdir = Utils\get_temp_dir()
						. uniqid( 'wp-cli-extract-zipfile-', true )
						. "-{$name}";

			$zip->extractTo( $tempdir );
			$zip->close();

			self::copy_overwrite_files(
				self::get_first_subfolder( $tempdir ),
				$dest
			);

			self::rmdir( $tempdir );
		} else {
			throw new Exception(
				sprintf(
					"ZipArchive failed to unzip '%s': %s.",
					$zipfile,
					self::zip_error_msg( $res )
				)
			);
		}
	}

	private static function extract_tarball( $tarball, $dest ) {
		if ( ! self::ensure_dir_exists( $dest ) ) {
			throw new Exception( "Could not create folder '{$dest}'." );
		}

		if ( class_exists( 'PharData' ) ) {
			try {
				$phar    = new PharData( $tarball );
				$name    = Utils\basename( $tarball );
				$tempdir = Utils\get_temp_dir()
							. uniqid( 'wp-cli-extract-tarball-', true )
							. "-{$name}";

				$phar->extractTo( $tempdir );

				self::copy_overwrite_files(
					self::get_first_subfolder( $tempdir ),
					$dest
				);

				self::rmdir( $tempdir );
				return;
			} catch ( Exception $e ) {
				WP_CLI::warning(
					"PharData failed, falling back to 'tar xz' ("
					. $e->getMessage() . ')'
				);
			}
		}

		if ( self::path_is_relative( $tarball ) ) {
			$tarball = "./{$tarball}";
		}

		if ( ! file_exists( $tarball )
			|| ! is_readable( $tarball )
			|| filesize( $tarball ) <= 0 ) {
			throw new Exception( "Invalid zip file '{$tarball}'." );
		}

		$cmd = Utils\esc_cmd(
			'tar xz --strip-components=1 --directory=%s -f %s',
			$dest,
			$tarball
		);

		$process_run = WP_CLI::launch(
			$cmd,
			false,  
			true  
		);

		if ( 0 !== $process_run->return_code ) {
			throw new Exception(
				sprintf(
					'Failed to execute `%s`: %s.',
					$cmd,
					self::tar_error_msg( $process_run )
				)
			);
		}
	}

	public static function copy_overwrite_files( $source, $dest ) {
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator(
				$source,
				RecursiveDirectoryIterator::SKIP_DOTS
			),
			RecursiveIteratorIterator::SELF_FIRST
		);

		$error = 0;

		if ( ! is_dir( $dest ) ) {
			mkdir( $dest, 0777, true );
		}

		foreach ( $iterator as $item ) {

			$dest_path = $dest . DIRECTORY_SEPARATOR . $iterator->getSubPathName();

			if ( $item->isDir() ) {
				if ( ! is_dir( $dest_path ) ) {
					mkdir( $dest_path );
				}
			} elseif ( file_exists( $dest_path ) && is_writable( $dest_path ) ) {
					copy( $item, $dest_path );
			} elseif ( ! file_exists( $dest_path ) ) {
				copy( $item, $dest_path );
			} else {
				$error = 1;
				WP_CLI::warning( "Unable to copy '" . $iterator->getSubPathName() . "' to current directory." );
			}
		}

		if ( $error ) {
			throw new Exception( 'There was an error overwriting existing files.' );
		}
	}

	public static function rmdir( $dir ) {
		$files = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator(
				$dir,
				RecursiveDirectoryIterator::SKIP_DOTS
			),
			RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $files as $fileinfo ) {
			$todo = $fileinfo->isDir() ? 'rmdir' : 'unlink';
			$path = $fileinfo->getRealPath();
			if ( 0 !== strpos( $path, $fileinfo->getRealPath() ) ) {
				WP_CLI::warning(
					"Temporary file or folder to be removed was found outside of temporary folder, aborting removal: '{$path}'"
				);
			}
			$todo( $path );
		}
		rmdir( $dir );
	}

	public static function zip_error_msg( $error_code ) {
		static $zip_err_msgs = [
			ZipArchive::ER_OK          => 'No error',
			ZipArchive::ER_MULTIDISK   => 'Multi-disk zip archives not supported',
			ZipArchive::ER_RENAME      => 'Renaming temporary file failed',
			ZipArchive::ER_CLOSE       => 'Closing zip archive failed',
			ZipArchive::ER_SEEK        => 'Seek error',
			ZipArchive::ER_READ        => 'Read error',
			ZipArchive::ER_WRITE       => 'Write error',
			ZipArchive::ER_CRC         => 'CRC error',
			ZipArchive::ER_ZIPCLOSED   => 'Containing zip archive was closed',
			ZipArchive::ER_NOENT       => 'No such file',
			ZipArchive::ER_EXISTS      => 'File already exists',
			ZipArchive::ER_OPEN        => 'Can\'t open file',
			ZipArchive::ER_TMPOPEN     => 'Failure to create temporary file',
			ZipArchive::ER_ZLIB        => 'Zlib error',
			ZipArchive::ER_MEMORY      => 'Malloc failure',
			ZipArchive::ER_CHANGED     => 'Entry has been changed',
			ZipArchive::ER_COMPNOTSUPP => 'Compression method not supported',
			ZipArchive::ER_EOF         => 'Premature EOF',
			ZipArchive::ER_INVAL       => 'Invalid argument',
			ZipArchive::ER_NOZIP       => 'Not a zip archive',
			ZipArchive::ER_INTERNAL    => 'Internal error',
			ZipArchive::ER_INCONS      => 'Zip archive inconsistent',
			ZipArchive::ER_REMOVE      => 'Can\'t remove file',
			ZipArchive::ER_DELETED     => 'Entry has been deleted',
		];

		if ( isset( $zip_err_msgs[ $error_code ] ) ) {
			return sprintf(
				'%s (%d)',
				$zip_err_msgs[ $error_code ],
				$error_code
			);
		}
		return $error_code;
	}

	public static function tar_error_msg( $process_run ) {
		$stderr = trim( $process_run->stderr );
		$nl_pos = strpos( $stderr, "\n" );
		if ( false !== $nl_pos ) {
			$stderr = trim( substr( $stderr, 0, $nl_pos ) );
		}
		if ( $stderr ) {
			return sprintf( '%s (%d)', $stderr, $process_run->return_code );
		}
		return $process_run->return_code;
	}

	private static function get_first_subfolder( $path ) {
		$iterator = new DirectoryIterator( $path );

		foreach ( $iterator as $fileinfo ) {
			if ( $fileinfo->isDir() && ! $fileinfo->isDot() ) {
				return "{$path}/{$fileinfo->getFilename()}";
			}
		}

		return $path;
	}

	private static function ensure_dir_exists( $dir ) {
		if ( ! is_dir( $dir ) ) {
			if ( ! @mkdir( $dir, 0777, true ) ) {
				$error = error_get_last();
				WP_CLI::warning(
					sprintf(
						"Failed to create directory '%s': %s.",
						$dir,
						$error['message']
					)
				);
				return false;
			}
		}

		return true;
	}

	private static function path_is_relative( $path ) {
		if ( '' === $path ) {
			return true;
		}

		$scheme_position = strpos( $path, '://' );
		if ( false !== $scheme_position ) {
			$path = substr( $path, $scheme_position + 3 );
		}

		if ( '/' === $path[0] || '\\' === $path[0] ) {
			return false;
		}

		if ( strlen( $path ) > 1 && ctype_alpha( $path[0] ) && ':' === $path[1] ) {

			if ( 2 === strlen( $path ) ) {
				return false;
			}

			if ( '/' === $path[2] || '\\' === $path[2] ) {
				return false;
			}
		}

		return true;
	}
}
