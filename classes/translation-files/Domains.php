<?php

namespace WPML\ST\TranslationFile;

use wpdb;
use WPML\Collect\Support\Collection;
use WPML\FP\Fns;
use WPML\FP\Just;
use WPML\FP\Maybe;
use WPML\FP\Nothing;
use WPML\FP\Relation;
use WPML\LIB\WP\Cache;
use WPML\ST\Package\Domains as PackageDomains;
use WPML_Admin_Texts;
use WPML_ST_Blog_Name_And_Description_Hooks;
use WPML_ST_Translations_File_Dictionary;
use WPML\ST\Shortcode;
use WPML\ST\TranslationFile\StringCollation;

class Domains {
	use StringCollation;

	const MO_DOMAINS_CACHE_GROUP = 'WPML_ST_CACHE';
	const MO_DOMAINS_CACHE_KEY   = 'wpml_string_translation_has_mo_domains';

	private $wpdb;

	private $package_domains;

	private $file_dictionary;

	private static $jed_domains;

	private static $mo_domains_invalidated = false;

	public function __construct(
		wpdb $wpdb,
		PackageDomains $package_domains,
		WPML_ST_Translations_File_Dictionary $file_dictionary
	) {
		$this->wpdb            = $wpdb;
		$this->package_domains = $package_domains;
		$this->file_dictionary = $file_dictionary;
	}


	public function getMODomains() {
		$transient_key = self::MO_DOMAINS_CACHE_KEY;

		$cacheItem = Cache::get( self::MO_DOMAINS_CACHE_GROUP, self::MO_DOMAINS_CACHE_KEY );
		if ( $cacheItem instanceof Just ) {
			return $cacheItem->get();
		}

		$transient = get_transient( $transient_key );
		if ( false !== $transient ) {
			return $transient;
		}

		$excluded_domains = self::getReservedDomains()->merge( $this->getJEDDomains() );

		$wpdb        = $this->wpdb;
		$mo_domains = wpml_collect(
			$wpdb->get_col(
				sprintf(
					"SELECT DISTINCT context %s
					FROM {$wpdb->prefix}icl_strings",
					esc_sql( $this->getCollateForContextColumn( $wpdb ) )
				)
			)
		)
			->diff( $excluded_domains )
			->values();

		$cacheLifeTime = $mo_domains->count() <= 0 ? 15 * MINUTE_IN_SECONDS : HOUR_IN_SECONDS;

		Cache::set(
			self::MO_DOMAINS_CACHE_GROUP,
			self::MO_DOMAINS_CACHE_KEY,
			$cacheLifeTime,
			$mo_domains
		);

		if ( ! wp_using_ext_object_cache() && ! self::isInvalidatedThisRequest() ) {
			set_transient( $transient_key, $mo_domains, $cacheLifeTime );
		}

		return $mo_domains;
	}

	public static function invalidateMODomainCache() {
		static $invalidationScheduled = false;

		if ( ! self::$mo_domains_invalidated ) {
			self::$mo_domains_invalidated = true;
			delete_transient( self::MO_DOMAINS_CACHE_KEY );
		}

		if ( ! $invalidationScheduled ) {
			$invalidationScheduled = true;
			add_action(
				'shutdown',
				function () {
					Cache::flushGroup( self::MO_DOMAINS_CACHE_GROUP );
				}
			);
		}
	}

	private static function isInvalidatedThisRequest() {
		return self::$mo_domains_invalidated;
	}



	public function getCustomMODomains( $locale = null ) {
		$locale            = $locale ?: determine_locale();
		$all_mo_domains    = $this->getMODomains();
		$native_mo_domains = $this->file_dictionary->get_domains( 'mo', $locale );

		return $all_mo_domains->reject(
			function ( $domain ) use ( $native_mo_domains ) {
				return null === $domain
					   || 0 === strpos( $domain, WPML_Admin_Texts::DOMAIN_NAME_PREFIX )
					   || $this->package_domains->isPackage( $domain )
					   || Shortcode::STRING_DOMAIN === $domain
					   || in_array( $domain, $native_mo_domains, true );
			}
		)->values();
	}

	public function getJEDDomains() {
		if ( ! self::$jed_domains instanceof Collection ) {
			self::$jed_domains = wpml_collect( $this->file_dictionary->get_domains( 'json' ) );
		}

		return self::$jed_domains;
	}

	public function hasNoNativeTranslationFile( $domain ) {
		return ! wpml_collect( $this->file_dictionary->get_domains() )
			->first( Relation::equals( $domain ) );
	}

	public static function resetCache() {
		self::invalidateMODomainCache();
		self::$jed_domains = null;
	}

	public static function getReservedDomains() {
		return wpml_collect(
			[
				WPML_ST_Blog_Name_And_Description_Hooks::STRING_DOMAIN,
			]
		);
	}

	private function getPackageDomains() {
		return $this->package_domains->getDomains();
	}
}
