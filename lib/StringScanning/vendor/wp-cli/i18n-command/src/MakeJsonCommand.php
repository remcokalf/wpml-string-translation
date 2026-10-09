<?php

namespace WP_CLI\I18n;

use Gettext\Extractors\Po as PoExtractor;
use Gettext\Generators\Po as PoGenerator;
use Gettext\Translation;
use Gettext\Translations;
use WP_CLI;
use WP_CLI_Command;
use WP_CLI\Utils;
use DirectoryIterator;
use IteratorIterator;
use SplFileInfo;

class MakeJsonCommand extends WP_CLI_Command {
	protected $json_options = 0;

	public function __invoke( $args, $assoc_args ) {
		$assoc_args      = Utils\parse_shell_arrays( $assoc_args, array( 'use-map' ) );
		$purge           = Utils\get_flag_value( $assoc_args, 'purge', true );
		$update_mo_files = Utils\get_flag_value( $assoc_args, 'update-mo-files', true );
		$map_paths       = Utils\get_flag_value( $assoc_args, 'use-map', false );
		$domain          = Utils\get_flag_value( $assoc_args, 'domain', '' );
		$extensions      = array_map(
			function ( $extension ) {
				return trim( $extension, ' .' );
			},
			explode( ',', Utils\get_flag_value( $assoc_args, 'extensions', '' ) )
		);

		if ( Utils\get_flag_value( $assoc_args, 'pretty-print', false ) ) {
			$this->json_options |= JSON_PRETTY_PRINT;
		}

		$source = realpath( $args[0] );

		if ( ! $source || ( ! is_file( $source ) && ! is_dir( $source ) ) ) {
			WP_CLI::error( 'Source file or directory does not exist.' );
		}

		$destination = is_file( $source ) ? dirname( $source ) : $source;

		if ( isset( $args[1] ) ) {
			$destination = $args[1];
		}

		$map = $this->build_map( $map_paths );
		if ( is_array( $map ) && empty( $map ) ) {
			WP_CLI::error( 'No valid keys found. No file was created.' );
		}

		if ( ! is_dir( $destination ) && ! mkdir( $destination, 0777, true ) ) {
			WP_CLI::error( 'Could not create destination directory.' );
		}

		$result_count = 0;

		if ( is_file( $source ) ) {
			$files = [ new SplFileInfo( $source ) ];
		} else {
			$files = new IteratorIterator( new DirectoryIterator( $source ) );
		}

		foreach ( $files as $file ) {
			if ( $file->isFile() && $file->isReadable() && 'po' === $file->getExtension() ) {
				$result        = $this->make_json( $file->getRealPath(), $destination, $map, $domain, $extensions );
				$result_count += count( $result );

				if ( $purge ) {
					$removed = $this->remove_js_strings_from_po_file( $file->getRealPath() );

					if ( ! $removed ) {
						WP_CLI::warning( sprintf( 'Could not update file %s', basename( $source ) ) );
						continue;
					}

					if ( $update_mo_files ) {
						$file_basename    = basename( $file->getFilename(), '.po' );
						$destination_file = "{$destination}/{$file_basename}.mo";

						$translations = Translations::fromPoFile( $file->getPathname() );
						if ( ! $translations->toMoFile( $destination_file ) ) {
							WP_CLI::warning( "Could not create file {$destination_file}" );
						}
					}
				}
			}
		}

		WP_CLI::success( sprintf( 'Created %d %s.', $result_count, Utils\pluralize( 'file', $result_count ) ) );
	}

	protected function build_map( $paths_or_maps ) {
		if ( false === $paths_or_maps ) {
			return null;
		}

		$map = [];

		if ( ! is_array( $paths_or_maps ) || empty( array_filter( array_keys( $paths_or_maps ), 'is_int' ) ) ) {
			$paths_or_maps = [ $paths_or_maps ];
		}
		$paths = array_filter( $paths_or_maps, 'is_string' );
		WP_CLI::debug( sprintf( 'Using %d map files: %s', count( $paths ), implode( ', ', $paths ) ), 'make-json' );
		$maps = array_filter( $paths_or_maps, 'is_array' );
		WP_CLI::debug( sprintf( 'Using %d inline map objects', count( $maps ) ), 'make-json' );
		WP_CLI::debug( sprintf( 'Dropping %d invalid values from map argument', count( $paths_or_maps ) - count( $paths ) - count( $maps ) ), 'make-json' );

		$to_transform = array_map(
			static function ( $value, $index ) {
				return [ $value, sprintf( 'inline object %d', $index ) ];
			},
			$maps,
			array_keys( $maps )
		);

		foreach ( $paths as $path ) {
			if ( ! file_exists( $path ) || is_dir( $path ) ) {
				WP_CLI::warning( sprintf( 'Map file %s does not exist', $path ) );
				continue;
			}

			$json = json_decode( file_get_contents( $path ), true );
			if ( ! is_array( $json ) ) {
				WP_CLI::warning( sprintf( 'Map file %s invalid', $path ) );
				continue;
			}

			$to_transform[] = [ $json, $path ];
		}

		foreach ( $to_transform as $transform ) {
			list( $json, $file ) = $transform;
			$key_num             = count( $json );
			$json = array_map(
				static function ( $value ) {
					if ( is_array( $value ) ) {
						$value = array_values( array_filter( $value, 'is_string' ) );
						if ( ! empty( $value ) ) {
							return $value;
						}
					}

					if ( is_string( $value ) ) {
						return [ $value ];
					}

					return null;
				},
				$json
			);
			WP_CLI::debug( sprintf( 'Dropped %d keys from %s', count( $json ) - $key_num, $file ), 'make-json' );

			$map = array_merge_recursive( $map, $json );
		}

		return $map;
	}

	protected function make_json( $source_file, $destination, $map, $domain, $extensions ) {
		$mapping      = [];
		$translations = new Translations();
		$result       = [];
		$extensions   = array_merge( [ 'js' ], $extensions );

		PoExtractor::fromFile( $source_file, $translations );

		$base_file_name = basename( $source_file, '.po' );

		$domain = ( ! empty( $domain ) ) ? $domain : $translations->getDomain();

		if ( $domain && 0 !== strpos( $base_file_name, $domain ) ) {
			$base_file_name = "{$domain}-{$base_file_name}";
		}

		foreach ( $translations as $translation ) {

			$sources = array_map(
				static function ( $reference ) use ( $extensions ) {
					$file      = $reference[0];
					$extension = pathinfo( $file, PATHINFO_EXTENSION );

					return in_array( $extension, $extensions, true )
						? preg_replace( "/.min.{$extension}$/", ".{$extension}", $file )
						: null;
				},
				$this->reference_map( $translation->getReferences(), $map )
			);

			$sources = array_unique( array_filter( $sources ) );

			foreach ( $sources as $source ) {
				if ( ! isset( $mapping[ $source ] ) ) {
					$mapping[ $source ] = new Translations();


					$mapping[ $source ]->setHeader( 'Language', $translations->getLanguage() );
					$mapping[ $source ]->setHeader( 'PO-Revision-Date', $translations->getHeader( 'PO-Revision-Date' ) );

					$plural_forms = $translations->getPluralForms();

					if ( $plural_forms ) {
						list( $count, $rule ) = $plural_forms;
						$mapping[ $source ]->setPluralForms( $count, $rule );
					}
				}

				$mapping[ $source ][] = $translation;
			}
		}

		$result += $this->build_json_files( $mapping, $base_file_name, $destination );

		return $result;
	}

	protected function reference_map( $references, $map ) {
		if ( is_null( $map ) ) {
			return $references;
		}

		$temp = array_map(
			static function ( $reference ) use ( &$map ) {
				$file = $reference[0];

				if ( array_key_exists( $file, $map ) ) {
					return $map[ $file ];
				}

				return null;
			},
			$references
		);
		$references = [];
		foreach ( $temp as $sources ) {
			if ( is_null( $sources ) ) {
				continue;
			}
			array_push( $references, ...$sources );
		}
		return array_map(
			static function ( $value ) {
				return [ $value ];
			},
			$references
		);
	}

	protected function build_json_files( $mapping, $base_file_name, $destination ) {
		$result = [];

		foreach ( $mapping as $file => $translations ) {

			$hash             = md5( $file );
			$destination_file = "{$destination}/{$base_file_name}-{$hash}.json";

			$success = JedGenerator::toFile(
				$translations,
				$destination_file,
				[
					'json'   => $this->json_options,
					'source' => $file,
				]
			);

			if ( ! $success ) {
				WP_CLI::warning( sprintf( 'Could not create file %s', basename( $destination_file, '.json' ) ) );

				continue;
			}

			$result[] = $destination_file;
		}

		return $result;
	}

	protected function remove_js_strings_from_po_file( $source_file ) {
		$translations = new Translations();

		PoExtractor::fromFile( $source_file, $translations );

		foreach ( $translations->getArrayCopy() as $translation ) {

			if ( ! $translation->hasReferences() ) {
				continue;
			}

			foreach ( $translation->getReferences() as $reference ) {
				$file = $reference[0];

				if ( substr( $file, - 3 ) !== '.js' ) {
					continue 2;
				}
			}

			unset( $translations[ $translation->getId() ] );
		}

		return PoGenerator::toFile( $translations, $source_file );
	}
}
