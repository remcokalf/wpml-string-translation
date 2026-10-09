<?php

namespace WP_CLI\I18n;

use DirectoryIterator;
use Gettext\Translations;
use IteratorIterator;
use SplFileInfo;
use WP_CLI;
use WP_CLI\Utils;
use WP_CLI_Command;

class MakePhpCommand extends WP_CLI_Command {
	public function __invoke( $args, $assoc_args ) {
		$source = realpath( $args[0] );
		if ( ! $source || ( ! is_file( $source ) && ! is_dir( $source ) ) ) {
			WP_CLI::error( 'Source file or directory does not exist.' );
		}

		$destination = is_file( $source ) ? dirname( $source ) : $source;
		if ( isset( $args[1] ) ) {
			$destination = $args[1];
		}

		if ( ! is_dir( $destination ) && ! mkdir( $destination, 0777, true ) ) {
			WP_CLI::error( 'Could not create destination directory.' );
		}

		if ( is_file( $source ) ) {
			$files = [ new SplFileInfo( $source ) ];
		} else {
			$files = new IteratorIterator( new DirectoryIterator( $source ) );
		}

		$result_count = 0;
		foreach ( $files as $file ) {
			if ( 'po' !== $file->getExtension() ) {
				continue;
			}

			if ( ! $file->isFile() || ! $file->isReadable() ) {
				WP_CLI::warning( sprintf( 'Could not read file %s', $file->getFilename() ) );
				continue;
			}

			$file_basename    = basename( $file->getFilename(), '.po' );
			$destination_file = "{$destination}/{$file_basename}.l10n.php";

			$translations = Translations::fromPoFile( $file->getPathname() );
			if ( ! PhpArrayGenerator::toFile( $translations, $destination_file ) ) {
				WP_CLI::warning( sprintf( 'Could not create file %s', $destination_file ) );
				continue;
			}

			++$result_count;
		}

		WP_CLI::success( sprintf( 'Created %d %s.', $result_count, Utils\pluralize( 'file', $result_count ) ) );
	}
}
