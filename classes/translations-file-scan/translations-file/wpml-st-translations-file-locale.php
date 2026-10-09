<?php

class WPML_ST_Translations_File_Locale {

	const PATTERN_SEARCH_LANG_JSON = '#DOMAIN_PLACEHOLDER(LOCALES_PLACEHOLDER)-[-_a-z0-9]+\.json$#i';

	private $sitepress;

	private $locale;

	public function __construct( SitePress $sitepress, WPML_Locale $locale ) {
		$this->sitepress = $sitepress;
		$this->locale    = $locale;
	}


	public function get( $filepath, $domain ) {
		switch ( $this->get_extension( $filepath ) ) {
			case 'mo':
				return $this->get_from_mo_file( $filepath );
			case 'json':
				return $this->get_from_json_file( $filepath, $domain );
			default:
				return '';
		}
	}

	private function get_extension( $filepath ) {
		return wpml_collect( pathinfo( $filepath ) )->get( 'extension', null );
	}

	private function get_from_mo_file( $filepath ) {
		return $this->get_locales()
					->first(
						function ( $locale ) use ( $filepath ) {
							return strpos( $filepath, $locale . '.mo' );
						},
						''
					);
	}

	private function get_from_json_file( $filepath, $domain ) {
		$original_domain = $this->get_original_domain_for_json( $filepath, $domain );
		$domain_replace  = 'default' === $original_domain ? '' : $original_domain . '-';
		$locales         = $this->get_locales()
			->map(
				function ( $locale ) {
					return preg_quote( $locale, '#' );
				}
			)
			->implode( '|' );

		$searches['native-file'] = '#' . preg_quote( $domain_replace, '#' ) . '(' . $locales . ')-[-_a-z0-9]+\.json$#i';
		$searches['wpml-file']   = '#' . preg_quote( $domain, '#' ) . '-(' . $locales . ').json#i';

		foreach ( $searches as $search ) {
			if ( preg_match( $search, $filepath, $matches ) && isset( $matches[1] ) ) {
				return $matches[1];
			}
		}

		return '';
	}

	private function get_original_domain_for_json( $filepath, $domain ) {
		$filename = basename( $filepath );

		if ( 0 === strpos( $domain, 'default' ) && 0 !== strpos( $filename, 'default' ) ) {
			return 'default';
		}

		$filename_parts        = explode( '-', $filename );
		$domain_parts          = explode( '-', $domain );
		$original_domain_parts = array();

		foreach ( $domain_parts as $i => $part ) {
			if ( $filename_parts[ $i ] !== $part ) {
				break;
			}

			$original_domain_parts[] = $part;
		}

		return implode( '-', $original_domain_parts );
	}

	private function get_locales() {
		return \wpml_collect( $this->sitepress->get_active_languages() )
			->keys()
			->map( [ $this->locale, 'get_locale' ] );
	}
}
