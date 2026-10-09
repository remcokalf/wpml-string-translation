<?php

namespace WPML\ST\PackageTranslation;

class Assign {
	public static function stringsFromDomainToExistingPackage( $domainName, $packageId ) {
		global $wpdb;

		$wpdb->update(
			$wpdb->prefix . 'icl_strings',
			[ 'string_package_id' => $packageId ],
			[ 'context' => $domainName ]
		);
	}

	public static function stringsFromDomainToNewPackage( $domainName, array $packageData ) {
		$packageId = \WPML_Package_Helper::create_new_package( new \WPML_Package( $packageData ) );
		if ( $packageId ) {
			self::stringsFromDomainToExistingPackage( $domainName, $packageId );
		}
	}
}
