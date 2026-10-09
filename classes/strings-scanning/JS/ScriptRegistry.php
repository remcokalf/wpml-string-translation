<?php

namespace WPML\ST\StringsScanning\JS;

use WPML\WP\OptionManager;

class ScriptRegistry {

	const OPTION_GROUP           = 'js_script_registry';
	const OPTION_KEY_PATHS       = 'paths';
	const OPTION_KEY_TEXTDOMAINS = 'textdomains';

	public static function register( $scriptsMap ) {
		$persistedPaths       = self::getPersistedPaths();
		$persistedTextDomains = self::getPersistedTextdomains();

		foreach ( (array) $scriptsMap as $handle => $src ) {
			$data = self::getRelativeFilePathAndDomain( $handle, $src );

			if ( $data ) {
				list( $path, $textDomain ) = $data;
				$persistedPaths[ $handle ] = $path;

				if ( $textDomain ) {
					$persistedTextDomains[ $handle ] = $textDomain;
				}
			}
		}

		$persistedPaths       = array_filter( $persistedPaths );
		$persistedTextDomains = array_filter( $persistedTextDomains );

		OptionManager::updateWithoutAutoLoad( self::OPTION_GROUP, self::OPTION_KEY_PATHS, $persistedPaths );
		OptionManager::updateWithoutAutoLoad( self::OPTION_GROUP, self::OPTION_KEY_TEXTDOMAINS, $persistedTextDomains );
	}

	private static function getRelativeFilePathAndDomain( $handle, $src ) {
		$wp_scripts = wp_scripts();

		$dep = $wp_scripts->registered[ $handle ] ?? null;
		$src = $dep->src ?? $src;

		if ( ! $src ) {
			return null;
		}

		if ( 0 === strpos( $src, '//' ) ) {
			$absURL = ( is_ssl() ? 'https:' : 'http:' ) . $src;
		} elseif ( preg_match( '#^https?://#i', $src ) ) {
			$absURL = $src;
		} elseif ( '/' === $src[0] ) {
			$absURL = home_url( $src );
		} else {
			$absURL = trailingslashit( $wp_scripts->base_url ) . $src;
		}

		$absHost  = wp_parse_url( $absURL, PHP_URL_HOST );
		$homeHost = wp_parse_url( home_url(), PHP_URL_HOST );

		$isExternalHost = $absHost && $homeHost && strcasecmp( $absHost, $homeHost ) !== 0;

		if ( $isExternalHost ) {
			return null;
		}

		$relURL  = wp_make_link_relative( $absURL );
		$relURL  = ltrim( (string) strtok( $relURL, '?' ) , '/' );
		$absPath = ABSPATH . $relURL;

		if ( file_exists( $absPath ) ) {
			$textdomain = $dep->textdomain ?? '';
			$textdomain = 'default' !== $textdomain ? $textdomain : '';

			return [ wp_normalize_path( $relURL ), $textdomain ];
		}

		return null;
	}

	public static function getAbsScriptPathsForComponents( $id, $type ) {
		$component_dir = false;

		if ( 'plugin' === $type ) {
			$parts         = explode( '/', $id );
			$component_dir = \WPML_ST_Path_Confinement::resolve_contained(
				WP_PLUGIN_DIR . '/' . $parts[0],
				WP_PLUGIN_DIR
			);
		} elseif ( 'theme' === $type ) {
			$theme = wp_get_theme( $id );
			if ( $theme->exists() ) {
				$component_dir = \WPML_ST_Path_Confinement::resolve_contained(
					$theme->get_stylesheet_directory(),
					$theme->get_theme_root()
				);
			}
		}

		if ( empty( $component_dir ) ) {
			return [];
		}

		return wpml_collect( self::getPersistedPaths() )
			->filter( function( $relFilePath ) use ( $component_dir ) {
				return false !== \WPML_ST_Path_Confinement::resolve_contained(
					ABSPATH . wp_normalize_path( $relFilePath ),
					$component_dir
				);
			} )
			->map( function( $relFilePath ) {
				return ABSPATH . $relFilePath;
			} )
			->values()
			->toArray();
	}

	public static function getHandleAndTextdomainByPath( $absPath ) {
		$handle = wpml_collect( self::getPersistedPaths() )
			->filter( function( $relPath ) use ( $absPath ) {
				return strpos( $absPath, $relPath ) !== false;
			} )
			->keys()
			->first();

		$textdomain = wpml_collect( self::getPersistedTextdomains() )
			->filter( function( $textdomain, $keyHandle ) use ( $handle ) {
				return $keyHandle === $handle;
			} )
			->first();

		return [ $handle, $textdomain ];
	}

	private static function getPersistedPaths() {
		return (array) OptionManager::getOr( [], self::OPTION_GROUP, self::OPTION_KEY_PATHS );
	}

	private static function getPersistedTextdomains() {
		return (array) OptionManager::getOr( [], self::OPTION_GROUP, self::OPTION_KEY_TEXTDOMAINS );
	}
}
