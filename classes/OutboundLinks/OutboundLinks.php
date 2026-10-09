<?php

namespace WPML\ST\OutboundLinks;

class OutboundLinks {

	const CORE_HELPER = '\WPML\OutboundLinks\OutboundLinks';

	public static function to( $url, array $args = array() ) {
		if ( class_exists( self::CORE_HELPER ) ) {
			return \WPML\OutboundLinks\OutboundLinks::to( $url, $args );
		}

		return $url;
	}
}
