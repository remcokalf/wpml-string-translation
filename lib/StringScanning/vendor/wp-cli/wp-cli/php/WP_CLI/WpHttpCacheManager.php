<?php

namespace WP_CLI;

use WP_CLI;

class WpHttpCacheManager {

	protected $whitelist = [];

	protected $cache;

	public function __construct( FileCache $cache ) {
		$this->cache = $cache;

		add_filter( 'pre_http_request', [ $this, 'filter_pre_http_request' ], 10, 3 );
		add_filter( 'http_response', [ $this, 'filter_http_response' ], 10, 3 );
	}

	public function filter_pre_http_request( $response, $args, $url ) {
		if ( ! isset( $this->whitelist[ $url ] ) ) {
			return $response;
		}
		if ( 'GET' !== $args['method'] || empty( $args['filename'] ) ) {
			return $response;
		}
		$filename = $this->cache->has( $this->whitelist[ $url ]['key'], $this->whitelist[ $url ]['ttl'] );
		if ( $filename ) {
			WP_CLI::log( sprintf( 'Using cached file \'%s\'...', $filename ) );
			if ( copy( $filename, $args['filename'] ) ) {
				return [
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
					'filename' => $args['filename'],
				];
			}

			WP_CLI::error( sprintf( 'Error copying cached file %s to %s', $filename, $url ) );
		}
		return $response;
	}


	public function filter_http_response( $response, $args, $url ) {
		if ( ! isset( $this->whitelist[ $url ] ) ) {
			return $response;
		}
		if ( 'GET' !== $args['method'] || empty( $args['filename'] ) ) {
			return $response;
		}
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return $response;
		}
		$this->cache->import( $this->whitelist[ $url ]['key'], $response['filename'] );
		return $response;
	}

	public function whitelist_package( $url, $group, $slug, $version, $ttl = null ) {
		$ext = pathinfo( Utils\parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION );
		$key = "$group/$slug-$version.$ext";
		$this->whitelist_url( $url, $key, $ttl );
		wp_update_plugins();
	}

	public function whitelist_url( $url, $key = null, $ttl = null ) {
		$key                     = $key ? : $url;
		$this->whitelist[ $url ] = [
			'key' => $key,
			'ttl' => $ttl,
		];
	}

	public function is_whitelisted( $url ) {
		return isset( $this->whitelist[ $url ] );
	}
}
