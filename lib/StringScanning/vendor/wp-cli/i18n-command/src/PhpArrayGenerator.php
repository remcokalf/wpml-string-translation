<?php

namespace WP_CLI\I18n;

use Gettext\Generators\PhpArray;
use Gettext\Translation;
use Gettext\Translations;

class PhpArrayGenerator extends PhpArray {
	public static $options = [
		'includeHeaders' => false,
	];

	public static function toString( Translations $translations, array $options = [] ) {
		$array = static::generate( $translations, $options );

		return '<?php' . PHP_EOL . 'return ' . static::var_export( $array ) . ';';
	}

	public static function generate( Translations $translations, array $options = [] ) {
		$options += static::$options;

		return static::toArray( $translations, $options['includeHeaders'] );
	}

	protected static function toArray( Translations $translations, $include_headers, $force_array = false ) {
		$messages = [];

		$result = [
			'domain'       => $translations->getDomain(),
			'plural-forms' => $translations->getHeader( 'Plural-Forms' ),
		];

		$language = $translations->getLanguage();
		if ( null !== $language ) {
			$result['language'] = $language;
		}

		$headers_allowlist = [
			'POT-Creation-Date'  => 'pot-creation-date',
			'PO-Revision-Date'   => 'po-revision-date',
			'Project-Id-Version' => 'project-id-version',
			'X-Generator'        => 'x-generator',
		];

		foreach ( $translations->getHeaders() as $name => $value ) {
			if ( isset( $headers_allowlist[ $name ] ) ) {
				$result[ $headers_allowlist[ $name ] ] = $value;
			}
		}

		foreach ( $translations as $translation ) {
			if ( $translation->isDisabled() || ! $translation->hasTranslation() ) {
				continue;
			}

			$context  = $translation->getContext();
			$original = $translation->getOriginal();

			$key = $context ? $context . "\4" . $original : $original;

			if ( $translation->hasPluralTranslations() ) {
				$msg_translations = $translation->getPluralTranslations();
				array_unshift( $msg_translations, $translation->getTranslation() );
				$messages[ $key ] = implode( "\0", $msg_translations );
			} else {
				$messages[ $key ] = $translation->getTranslation();
			}
		}

		$result['messages'] = $messages;

		return $result;
	}

	private static function array_is_list( array $arr ) {
		if ( function_exists( 'array_is_list' ) ) {
			return array_is_list( $arr );
		}

		if ( ( array() === $arr ) || ( array_values( $arr ) === $arr ) ) {
			return true;
		}

		$next_key = -1;

		foreach ( $arr as $k => $v ) {
			if ( ++$next_key !== $k ) {
				return false;
			}
		}

		return true;
	}

	private static function var_export( $value ) {
		if ( ! is_array( $value ) ) {
			return var_export( $value, true );
		}

		$entries = array();

		$is_list = self::array_is_list( $value );

		foreach ( $value as $key => $val ) {
			$entries[] = $is_list ? self::var_export( $val ) : var_export( $key, true ) . '=>' . self::var_export( $val );
		}

		return '[' . implode( ',', $entries ) . ']';
	}
}
