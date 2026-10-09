<?php

namespace WP_CLI;

use RuntimeException;

final class WpOrgApi {

	const API_ROOT = 'https://api.wordpress.org';

	const DOWNLOADS_ROOT = 'https://downloads.wordpress.org';

	const CORE_CHECKSUMS_ENDPOINT = self::API_ROOT . '/core/checksums/1.0/';

	const PLUGIN_CHECKSUMS_ENDPOINT = self::DOWNLOADS_ROOT . '/plugin-checksums/';

	const PLUGIN_INFO_ENDPOINT = self::API_ROOT . '/plugins/info/1.2/';

	const THEME_INFO_ENDPOINT = self::API_ROOT . '/themes/info/1.2/';

	const SALT_ENDPOINT = self::API_ROOT . '/secret-key/1.1/salt/';

	const VERSION_CHECK_ENDPOINT = self::API_ROOT . '/core/version-check/1.7/';

	private $options;

	public function __construct( $options = [] ) {
		$this->options = $options;
	}

	public function get_core_checksums( $version, $locale = 'en_US' ) {
		$data = [
			'version' => $version,
			'locale'  => $locale,
		];

		$url = sprintf(
			'%s?%s',
			self::CORE_CHECKSUMS_ENDPOINT,
			http_build_query( $data, '', '&' )
		);

		$response = $this->json_get_request( $url );

		if (
			! is_array( $response )
			|| ! isset( $response['checksums'] )
			|| ! is_array( $response['checksums'] )
		) {
			return false;
		}

		return $response['checksums'];
	}

	public function get_core_version_check( $locale = 'en_US' ) {
		$url = sprintf(
			'%s?%s',
			self::VERSION_CHECK_ENDPOINT,
			http_build_query( [ 'locale' => $locale ], '', '&' )
		);

		$response = $this->json_get_request( $url );

		if ( ! is_array( $response ) ) {
			return false;
		}

		return $response;
	}

	public function get_core_download_offer( $locale = 'en_US' ) {
		$response = $this->get_core_version_check( $locale );

		if (
			! is_array( $response )
			|| ! isset( $response['offers'] )
			|| ! is_array( $response['offers'] )
		) {
			return false;
		}

		$offer = $response['offers'][0];

		if ( ! array_key_exists( 'locale', $offer ) || $locale !== $offer['locale'] ) {
			return false;
		}

		return $offer;
	}

	public function get_plugin_checksums( $plugin, $version ) {
		$url = sprintf(
			'%s%s/%s.json',
			self::PLUGIN_CHECKSUMS_ENDPOINT,
			$plugin,
			$version
		);

		$response = $this->json_get_request( $url );

		if (
			! is_array( $response )
			|| ! isset( $response['files'] )
			|| ! is_array( $response['files'] )
		) {
			return false;
		}

		return $response['files'];
	}

	public function get_plugin_info( $plugin, $locale = 'en_US', array $fields = [] ) {
		$action  = 'plugin_information';
		$request = [
			'locale' => $locale,
			'slug'   => $plugin,
		];

		if ( ! empty( $fields ) ) {
			$request['fields'] = $fields;
		}

		$url = sprintf(
			'%s?%s',
			self::PLUGIN_INFO_ENDPOINT,
			http_build_query( compact( 'action', 'request' ), '', '&' )
		);

		$response = $this->json_get_request( $url );

		if ( ! is_array( $response ) ) {
			return false;
		}

		return $response;
	}

	public function get_theme_info( $theme, $locale = 'en_US', array $fields = [] ) {
		$action  = 'theme_information';
		$request = [
			'locale' => $locale,
			'slug'   => $theme,
		];

		if ( ! empty( $fields ) ) {
			$request['fields'] = $fields;
		}

		$url = sprintf(
			'%s?%s',
			self::THEME_INFO_ENDPOINT,
			http_build_query( compact( 'action', 'request' ), '', '&' )
		);

		$response = $this->json_get_request( $url );

		if ( ! is_array( $response ) ) {
			return false;
		}

		return $response;
	}

	public function get_salts() {
		return $this->get_request( self::SALT_ENDPOINT );
	}

	private function json_get_request( $url, $headers = [], $options = [] ) {
		$headers = array_merge(
			[
				'Accept' => 'application/json',
			],
			$headers
		);

		$response = $this->get_request( $url, $headers, $options );

		if ( false === $response ) {
			return $response;
		}

		$data = json_decode( $response, true );

		if ( JSON_ERROR_NONE !== json_last_error() ) {
			throw new RuntimeException( 'Failed to decode JSON: ' . json_last_error_msg() );
		}

		return $data;
	}

	private function get_request( $url, $headers = [], $options = [] ) {
		$options = array_merge(
			$this->options,
			[
				'halt_on_error' => false,
			],
			$options
		);

		$response = Utils\http_request( 'GET', $url, null, $headers, $options );

		if (
			! $response->success
			|| 200 > (int) $response->status_code
			|| 300 <= $response->status_code
		) {
			throw new RuntimeException(
				"Couldn't fetch response from {$url} (HTTP code {$response->status_code})."
			);
		}

		return trim( $response->body );
	}
}
