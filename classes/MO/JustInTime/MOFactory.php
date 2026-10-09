<?php

namespace WPML\ST\MO\JustInTime;

use WPML\ST\MO\LoadedMODictionary;

class MOFactory {

	private $loaded_mo_dictionary;

	public function __construct( LoadedMODictionary $loaded_mo_dictionary ) {
		$this->loaded_mo_dictionary = $loaded_mo_dictionary;
	}

	public function get( $locale, array $excluded_domains, array $cachedMoObjects ) {
		$mo_objects = [
			'default' => isset( $cachedMoObjects['default'] )
				? $cachedMoObjects['default']
				: new DefaultMO( $this->loaded_mo_dictionary, $locale ),
		];

		$excluded_domains[] = 'default';

		foreach ( $this->loaded_mo_dictionary->getDomains( $excluded_domains ) as $domain ) {
			$mo_objects[ $domain ] = isset( $cachedMoObjects[ $domain ] )
				? $cachedMoObjects[ $domain ]
				: new MO( $this->loaded_mo_dictionary, $locale, $domain );
		}

		return $mo_objects;
	}
}
