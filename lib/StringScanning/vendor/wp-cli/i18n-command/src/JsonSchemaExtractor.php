<?php

namespace WP_CLI\I18n;

use Gettext\Extractors\Extractor;
use Gettext\Translations;
use WP_CLI;
use WP_CLI\Utils;

class JsonSchemaExtractor extends Extractor {
	use IterableCodeExtractor;

	const THEME_JSON_SOURCE = 'https://develop.svn.wordpress.org/trunk/src/wp-includes/theme-i18n.json';

	const THEME_JSON_FALLBACK = __DIR__ . '/../assets/theme-i18n.json';

	const BLOCK_JSON_SOURCE = 'https://develop.svn.wordpress.org/trunk/src/wp-includes/block-i18n.json';

	const BLOCK_JSON_FALLBACK = __DIR__ . '/../assets/block-i18n.json';

	protected static $schema_cache = [];

	protected static function load_schema( $schema, $fallback ) {
		if ( ! empty( self::$schema_cache[ $schema ] ) ) {
			return self::$schema_cache[ $schema ];
		}

		$json = self::remote_get( $schema );

		if ( empty( $json ) ) {
			WP_CLI::debug( 'Remote file could not be accessed, will use local file as fallback', 'make-pot' );
			$json = file_get_contents( $fallback );
		}

		$file_structure = json_decode( $json, false );

		if ( JSON_ERROR_NONE !== json_last_error() ) {
			WP_CLI::debug( 'Error when decoding theme-i18n.json file', 'make-pot' );
			return [];
		}

		if ( ! is_object( $file_structure ) ) {
			return [];
		}

		self::$schema_cache[ $schema ] = $file_structure;

		return $file_structure;
	}

	public static function fromString( $text, Translations $translations, array $options = [] ) {
		$file = $options['file'];
		WP_CLI::debug( "Parsing file {$file}", 'make-pot' );

		$schema = self::load_schema( $options['schema'], $options['schemaFallback'] );

		$json = json_decode( $text, true );

		if ( null === $json ) {
			WP_CLI::debug(
				sprintf(
					'Could not parse file %1$s: error code %2$s',
					$file,
					json_last_error()
				),
				'make-pot'
			);

			return;
		}

		self::extract_strings_using_i18n_schema(
			$translations,
			$options['addReferences'] ? $file : null,
			$schema,
			$json
		);
	}

	private static function extract_strings_using_i18n_schema( Translations $translations, $file, $i18n_schema, $settings ) {
		if ( empty( $i18n_schema ) || empty( $settings ) ) {
			return;
		}

		if ( is_string( $i18n_schema ) && is_string( $settings ) ) {
			$translation = $translations->insert( $i18n_schema, $settings );

			if ( $file ) {
				$translation->addReference( $file );
			}

			return;
		}

		if ( is_array( $i18n_schema ) && is_array( $settings ) ) {
			foreach ( $settings as $value ) {
				self::extract_strings_using_i18n_schema( $translations, $file, $i18n_schema[0], $value );
			}
		}
		if ( is_object( $i18n_schema ) && is_array( $settings ) ) {
			$group_key = '*';
			foreach ( $settings as $key => $value ) {
				if ( isset( $i18n_schema->$key ) ) {
					self::extract_strings_using_i18n_schema( $translations, $file, $i18n_schema->$key, $value );
				} elseif ( isset( $i18n_schema->$group_key ) ) {
					self::extract_strings_using_i18n_schema( $translations, $file, $i18n_schema->$group_key, $value );
				}
			}
		}
	}

	private static function remote_get( $url ) {
		if ( ! $url ) {
			return '';
		}

		$headers  = [ 'Content-type: application/json' ];
		$options  = [ 'halt_on_error' => false ];
		$response = Utils\http_request( 'GET', $url, null, $headers, $options );

		if (
			! $response->success
			|| 200 > (int) $response->status_code
			|| 300 <= (int) $response->status_code
		) {
			WP_CLI::debug( "Failed to download from URL {$url}", 'make-pot' );
			return '';
		}

		return trim( $response->body );
	}
}
