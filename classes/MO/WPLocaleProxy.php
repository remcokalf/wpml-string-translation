<?php

namespace WPML\ST\MO;

use WP_Locale;

class WPLocaleProxy {

	private $wp_locale;

	public function __call( $method, array $args ) {
		$callback = [ $this->getWPLocale(), $method ];
		if ( method_exists( $this->getWPLocale(), $method ) && is_callable( $callback ) ) {
			return call_user_func_array( $callback , $args );
		}

		return null;
	}

	public function __isset( $property ) {
		if ( property_exists( \WP_Locale::class, $property ) ) {
			return true;
		}

		return false;
	}

	public function __get( $property ) {
		if ( $this->__isset( $property ) ) {
			return $this->getWPLocale()->{$property};
		}

		return null;
	}

	private function getWPLocale() {
		if ( ! $this->wp_locale ) {
			$this->wp_locale = new WP_Locale();
		}

		return $this->wp_locale;
	}
}
