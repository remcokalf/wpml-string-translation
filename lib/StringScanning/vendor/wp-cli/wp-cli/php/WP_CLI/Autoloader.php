<?php

namespace WP_CLI;

class Autoloader {

	protected $namespaces = [];

	public function __destruct() {
		$this->unregister();
	}

	public function register() {
		spl_autoload_register( [ $this, 'autoload' ] );
	}

	public function unregister() {
		spl_autoload_unregister( [ $this, 'autoload' ] );
	}

	public function add_namespace(
		$root,
		$base_dir,
		$prefix = '',
		$suffix = '.php',
		$lowercase = false,
		$underscores = false
	) {
		$this->namespaces[] = [
			'root'        => $this->normalize_root( (string) $root ),
			'base_dir'    => $this->add_trailing_slash( (string) $base_dir ),
			'prefix'      => (string) $prefix,
			'suffix'      => (string) $suffix,
			'lowercase'   => (bool) $lowercase,
			'underscores' => (bool) $underscores,
		];

		return $this;
	}

	public function autoload( $class ) {

		foreach ( $this->namespaces as $namespace ) {

			if ( 0 !== strpos( $class, $namespace['root'] ) ) {
				continue;
			}

			$filename = str_replace(
				[ $namespace['root'], '\\' ],
				[ '', DIRECTORY_SEPARATOR ],
				$class
			);

			$filename = $this->remove_leading_backslash( $filename );

			if ( $namespace['lowercase'] ) {
				$filename = strtolower( $filename );
			}

			if ( $namespace['underscores'] ) {
				$filename = str_replace( '_', '-', $filename );
			}

			$filepath = $namespace['base_dir']
				. $namespace['prefix']
				. $filename
				. $namespace['suffix'];

			if ( is_readable( $filepath ) ) {
				require_once $filepath;
			}
		}
	}

	protected function normalize_root( $root ) {
		$root = $this->remove_leading_backslash( $root );

		return $this->add_trailing_backslash( $root );
	}

	protected function remove_leading_backslash( $string ) {
		return ltrim( $string, '\\' );
	}

	protected function add_trailing_backslash( $string ) {
		return rtrim( $string, '\\' ) . '\\';
	}

	protected function add_trailing_slash( $string ) {
		return rtrim( $string, '/\\' ) . '/';
	}
}
