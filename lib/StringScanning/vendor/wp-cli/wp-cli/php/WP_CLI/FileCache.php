<?php

/*
 * This file is heavily inspired and use code from Composer(getcomposer.org),
 * in particular Composer/Cache and Composer/Util/FileSystem from 1.0.0-alpha7
 *
 * The original code and this file are both released under MIT license.
 *
 * The copyright holders of the original code are:
 * (c) Nils Adermann <naderman@naderman.de>
 *     Jordi Boggiano <j.boggiano@seld.be>
 */

namespace WP_CLI;

use DateTime;
use Exception;
use Symfony\Component\Finder\Finder;
use WP_CLI;

class FileCache {

	protected $root;
	protected $enabled = true;
	protected $ttl;
	protected $max_size;
	protected $whitelist;

	public function __construct( $cache_dir, $ttl, $max_size, $whitelist = 'a-z0-9._-' ) {
		$this->root      = Utils\trailingslashit( $cache_dir );
		$this->ttl       = (int) $ttl;
		$this->max_size  = (int) $max_size;
		$this->whitelist = $whitelist;

		if ( ! $this->ensure_dir_exists( $this->root ) ) {
			$this->enabled = false;
		}
	}

	public function is_enabled() {
		return $this->enabled;
	}

	public function get_root() {
		return $this->root;
	}


	public function has( $key, $ttl = null ) {
		if ( ! $this->enabled ) {
			return false;
		}

		$filename = $this->filename( $key );

		if ( ! file_exists( $filename ) ) {
			return false;
		}

		if ( null === $ttl ) {
			$ttl = $this->ttl;
		} elseif ( $this->ttl > 0 ) {
			$ttl = min( (int) $ttl, $this->ttl );
		} else {
			$ttl = (int) $ttl;
		}

		if ( $ttl > 0 && ( filemtime( $filename ) + $ttl ) < time() ) {
			if ( $this->ttl > 0 && $ttl >= $this->ttl ) {
				unlink( $filename );
			}
			return false;
		}

		return $filename;
	}

	public function write( $key, $contents ) {
		$filename = $this->prepare_write( $key );

		if ( $filename ) {
			return file_put_contents( $filename, $contents ) && touch( $filename );
		}

		return false;
	}

	public function read( $key, $ttl = null ) {
		$filename = $this->has( $key, $ttl );

		if ( $filename ) {
			return file_get_contents( $filename );
		}

		return false;
	}

	public function import( $key, $source ) {
		$filename = $this->prepare_write( $key );

		if ( ! is_readable( $source ) ) {
			return false;
		}

		if ( $filename ) {
			return copy( $source, $filename ) && touch( $filename );
		}

		return false;
	}

	public function export( $key, $target, $ttl = null ) {
		$filename = $this->has( $key, $ttl );

		if ( $filename && $this->ensure_dir_exists( dirname( $target ) ) ) {
			return copy( $filename, $target );
		}

		return false;
	}

	public function remove( $key ) {
		if ( ! $this->enabled ) {
			return false;
		}

		$filename = $this->filename( $key );

		if ( file_exists( $filename ) ) {
			return unlink( $filename );
		}

		return false;
	}

	public function clean() {
		if ( ! $this->enabled ) {
			return false;
		}

		$ttl      = $this->ttl;
		$max_size = $this->max_size;

		if ( $ttl > 0 ) {
			try {
				$expire = new DateTime();
				$expire->modify( '-' . $ttl . ' seconds' );

				$finder = $this->get_finder()->date( 'until ' . $expire->format( 'Y-m-d H:i:s' ) );
				foreach ( $finder as $file ) {
					unlink( $file->getRealPath() );
				}
			} catch ( Exception $e ) {
				WP_CLI::error( $e->getMessage() );
			}
		}

		if ( $max_size > 0 ) {
			$files = array_reverse( iterator_to_array( $this->get_finder()->sortByAccessedTime()->getIterator() ) );
			$total = 0;

			foreach ( $files as $file ) {
				if ( ( $total + $file->getSize() ) <= $max_size ) {
					$total += $file->getSize();
				} else {
					unlink( $file->getRealPath() );
				}
			}
		}

		return true;
	}

	public function clear() {
		if ( ! $this->enabled ) {
			return false;
		}

		$finder = $this->get_finder();

		foreach ( $finder as $file ) {
			unlink( $file->getRealPath() );
		}

		return true;
	}

	public function prune() {
		if ( ! $this->enabled ) {
			return false;
		}

		$finder = $this->get_finder()->sortByName();

		$files_to_delete = [];

		foreach ( $finder as $file ) {
			$pieces    = explode( '-', $file->getBasename( $file->getExtension() ) );
			$timestamp = end( $pieces );

			if ( ! is_numeric( $timestamp ) ) {
				continue;
			}

			$basename_without_timestamp = str_replace( '-' . $timestamp, '', $file->getBasename() );

			if ( isset( $files_to_delete[ $basename_without_timestamp ] ) ) {
				unlink( $files_to_delete[ $basename_without_timestamp ] );
			}

			$files_to_delete[ $basename_without_timestamp ] = $file->getRealPath();
		}

		return true;
	}

	protected function ensure_dir_exists( $dir ) {
		if ( ! is_dir( $dir ) ) {
			if ( preg_match( '{(^|[\\\\/])(\$null|nul|NUL|/dev/null)([\\\\/]|$)}', $dir ) ) {
				return false;
			}

			if ( ! @mkdir( $dir, 0777, true ) ) {
				$message = "Failed to create directory '{$dir}'";
				$error   = error_get_last();
				if ( is_array( $error ) ) {
					$message .= ": {$error['message']}";
				}
				WP_CLI::warning( "{$message}." );
				return false;
			}
		}

		return true;
	}

	protected function prepare_write( $key ) {
		if ( ! $this->enabled ) {
			return false;
		}

		$filename = $this->filename( $key );

		if ( ! $this->ensure_dir_exists( dirname( $filename ) ) ) {
			return false;
		}

		return $filename;
	}

	protected function validate_key( $key ) {
		$url_parts = Utils\parse_url( $key, -1, false );
		if ( array_key_exists( 'path', $url_parts ) && ! empty( $url_parts['scheme'] ) ) {
			$parts   = [ 'misc' ];
			$parts[] = $url_parts['scheme'] .
				( empty( $url_parts['host'] ) ? '' : '-' . $url_parts['host'] ) .
				( empty( $url_parts['port'] ) ? '' : '-' . $url_parts['port'] );
			$parts[] = substr( $url_parts['path'], 1 ) .
				( empty( $url_parts['query'] ) ? '' : '-' . $url_parts['query'] );
		} else {
			$key   = str_replace( '\\', '/', $key );
			$parts = explode( '/', ltrim( $key ) );
		}

		$parts = preg_replace( "#[^{$this->whitelist}]#i", '-', $parts );

		return rtrim( implode( '/', $parts ), '.' );
	}

	protected function filename( $key ) {
		return $this->root . $this->validate_key( $key );
	}

	protected function get_finder() {
		return Finder::create()->in( $this->root )->files();
	}
}
