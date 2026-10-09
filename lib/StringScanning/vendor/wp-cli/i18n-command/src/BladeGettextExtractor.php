<?php

namespace WP_CLI\I18n;

use eftec\bladeone\BladeOne;


class BladeGettextExtractor extends \Gettext\Extractors\PhpCode {

	protected static function getBladeCompiler() {
		$cache_path     = empty( $options['cachePath'] ) ? sys_get_temp_dir() : $options['cachePath'];
		$blade_compiler = new BladeOne( null, $cache_path );

		if ( method_exists( $blade_compiler, 'withoutComponentTags' ) ) {
			$blade_compiler->withoutComponentTags();
		}

		return $blade_compiler;
	}

	protected static function compileBladeToPhp( $text ) {
		return static::getBladeCompiler()->compileString( $text );
	}

	public static function fromStringMultiple( $text, array $translations, array $options = [] ) {
		$php_string = static::compileBladeToPhp( $text );
		return parent::fromStringMultiple( $php_string, $translations, $options );
	}
}
