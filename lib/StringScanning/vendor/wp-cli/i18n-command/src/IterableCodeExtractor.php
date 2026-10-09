<?php

namespace WP_CLI\I18n;

use Gettext\Translation;
use Gettext\Translations;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use WP_CLI;
use WP_CLI\Utils;

trait IterableCodeExtractor {

	protected static $dir = '';

	public static function fromFile( $file_or_files, Translations $translations, array $options = [] ) {
		foreach ( static::getFiles( $file_or_files ) as $file ) {
			if ( ! empty( $options['restrictFileNames'] ) ) {
				$basename = Utils\basename( $file );
				if ( ! in_array( $basename, $options['restrictFileNames'], true ) ) {
					continue;
				}
			}

			$relative_file_path = ltrim( str_replace( static::$dir, '', Utils\normalize_path( $file ) ), '/' );

			$options['file'] = $relative_file_path;

			if ( ! empty( $options['restrictDirectories'] ) ) {
				$top_level_dirname = explode( '/', $relative_file_path )[0];

				if ( ! in_array( $top_level_dirname, $options['restrictDirectories'], true ) ) {
					continue;
				}
			}

			$text = file_get_contents( $file );

			if ( ! $text ) {
				WP_CLI::debug(
					sprintf(
						'Could not load file %1s',
						$file
					),
					'make-pot'
				);

				continue;
			}

			if ( ! empty( $options['wpExtractTemplates'] ) ) {
				$headers = FileDataExtractor::get_file_data_from_string( $text, [ 'Template Name' => 'Template Name' ] );

				if ( ! empty( $headers['Template Name'] ) ) {
					$translation = new Translation( '', $headers['Template Name'] );
					$translation->addExtractedComment( 'Template Name of the theme' );

					$translations[] = $translation;
				}
			}

			if ( ! empty( $options['wpExtractPatterns'] ) && 0 === strpos( $options['file'], 'patterns/' ) ) {
				$headers = FileDataExtractor::get_file_data_from_string(
					$text,
					[
						'Title'       => 'Title',
						'Description' => 'Description',
					]
				);

				if ( ! empty( $headers['Title'] ) ) {
					$translation = new Translation( 'Pattern title', $headers['Title'] );
					$translation->addReference( $options['file'] );

					$translations[] = $translation;
				}

				if ( ! empty( $headers['Description'] ) ) {
					$translation = new Translation( 'Pattern description', $headers['Description'] );
					$translation->addReference( $options['file'] );

					$translations[] = $translation;
				}
			}

			static::fromString( $text, $translations, $options );
		}
	}

	public static function fromDirectory( $dir, Translations $translations, array $options = [] ) {
		$dir = Utils\normalize_path( $dir );

		static::$dir = $dir;

		$include = isset( $options['include'] ) ? $options['include'] : [];
		$exclude = isset( $options['exclude'] ) ? $options['exclude'] : [];

		$files = static::getFilesFromDirectory( $dir, $include, $exclude, $options['extensions'] );

		if ( ! empty( $files ) ) {
			static::fromFile( $files, $translations, $options );
		}

		static::$dir = '';
	}

	protected static function calculateMatchScore( SplFileInfo $file, array $matchers = [] ) {
		if ( empty( $matchers ) ) {
			return 0;
		}

		if ( in_array( $file->getBasename(), $matchers, true ) ) {
			return 10;
		}

		$root_relative_path = str_replace( static::$dir, '', $file->getPathname() );

		foreach ( $matchers as $path_or_file ) {
			$pattern = preg_quote( str_replace( '*', '__wildcard__', $path_or_file ), '#' );
			$pattern = '(^|/)' . str_replace( '__wildcard__', '(.+)', $pattern );

			$base_score = count(
				array_filter(
					explode( '/', $path_or_file ),
					static function ( $component ) {
						return '*' !== $component;
					}
				)
			);
			if ( 0 === $base_score ) {
				$base_score = 0.2;
			}

			if (
				false === strpos( $path_or_file, '*' ) &&
				preg_match( '#' . $pattern . '$#', $root_relative_path )
			) {
				return $base_score * 10;
			}

			if ( preg_match( '#' . $pattern . '(/|$)#', $root_relative_path ) ) {
				return $base_score;
			}
		}

		return 0;
	}

	protected static function containsMatchingChildren( SplFileInfo $dir, array $matchers = [] ) {
		if ( empty( $matchers ) ) {
			return false;
		}

		$root_relative_path = str_replace( static::$dir, '', $dir->getPathname() );
		$root_relative_path = static::trim_leading_slash( $root_relative_path );

		foreach ( $matchers as $path_or_file ) {
			if (
				'' !== $root_relative_path &&
				false === strpos( $path_or_file, '*' ) &&
				0 === strpos( $path_or_file . '/', $root_relative_path )
			) {
				return true;
			}

			$base = current( explode( '*', $path_or_file ) );

			if (
				( '' !== $root_relative_path && 0 === strpos( $base, $root_relative_path ) ) ||
				( '' !== $base && 0 === strpos( $root_relative_path, $base ) )
			) {
				return true;
			}
		}

		return false;
	}

	public static function getFilesFromDirectory( $dir, array $includes = [], array $excludes = [], $extensions = [] ) {
		$filtered_files = [];

		$files = new RecursiveIteratorIterator(
			new RecursiveCallbackFilterIterator(
				new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS | RecursiveDirectoryIterator::UNIX_PATHS | RecursiveDirectoryIterator::FOLLOW_SYMLINKS ),
				static function ( $file, $key, $iterator ) use ( $includes, $excludes, $extensions ) {

					$includes = array_map( self::class . '::trim_leading_slash', $includes );
					$excludes = array_map( self::class . '::trim_leading_slash', $excludes );

					$inclusion_score = empty( $includes ) ? 0.1 : static::calculateMatchScore( $file, $includes );
					$exclusion_score = static::calculateMatchScore( $file, $excludes );

					if ( 0 === $exclusion_score && $iterator->hasChildren() ) {
						return true;
					}

					if ( ( 0 === $inclusion_score || $exclusion_score > $inclusion_score ) && $iterator->hasChildren() ) {
						return static::containsMatchingChildren( $file, $includes );
					}

					if ( $exclusion_score > 0 && $inclusion_score >= $exclusion_score && $iterator->hasChildren() ) {
						return true;
					}

					if ( ! $file->isFile() || ! static::file_has_file_extension( $file, $extensions ) ) {
						return false;
					}

					return $inclusion_score > $exclusion_score;
				}
			),
			RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $files as $file ) {
			if ( ! $file->isFile() || ! static::file_has_file_extension( $file, $extensions ) ) {
				continue;
			}

			$filtered_files[] = Utils\normalize_path( $file->getPathname() );
		}

		sort( $filtered_files, SORT_NATURAL | SORT_FLAG_CASE );

		return $filtered_files;
	}

	protected static function file_has_file_extension( $file, $extensions ) {
		return in_array( $file->getExtension(), $extensions, true ) ||
			in_array( static::file_get_extension_multi( $file ), $extensions, true );
	}

	protected static function file_get_extension_multi( $file ) {
		$file_extension_separator = '.';

		$filename = $file->getFilename();
		$parts    = explode( $file_extension_separator, $filename, 2 );
		if ( count( $parts ) <= 1 ) {
			return $file->getExtension();
		}
		return $parts[1];
	}

	protected static function trim_leading_slash( $path ) {
		return ltrim( $path, '/' );
	}
}
