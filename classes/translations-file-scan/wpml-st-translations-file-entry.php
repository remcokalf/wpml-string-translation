<?php

class WPML_ST_Translations_File_Entry {

	const NOT_IMPORTED    = 'not_imported';
	const IMPORTED        = 'imported';
	const PARTLY_IMPORTED = 'partly_imported';
	const FINISHED        = 'finished';
	const SKIPPED         = 'skipped';

	const PATTERN_SEARCH_LANG_MO   = '#[-]?([a-z]+[_A-Z]*)\.mo$#i';
	const PATTERN_SEARCH_LANG_JSON = '#([a-z]+[_A-Z]*)-[-a-z0-9]+\.json$#i';

	private $path;

	private $domain;

	private $status;

	private $imported_strings_count = 0;

	private $last_modified;

	private $component_type;

	private $component_id;

	public function __construct( $path, $domain, $status = self::NOT_IMPORTED ) {
		if ( ! is_string( $path ) ) {
			throw new InvalidArgumentException( 'MO File path must be string type' );
		}
		if ( ! is_string( $domain ) ) {
			throw new InvalidArgumentException( 'MO File domain must be string type' );
		}

		$this->path   = $this->convert_to_relative_path( $path );
		$this->domain = $domain;

		$this->validate_status( $status );
		$this->status = $status;
	}

	private function convert_to_relative_path( $path ) {
		$parts = explode( DIRECTORY_SEPARATOR, $this->fix_dir_separator( WP_CONTENT_DIR ) );

		return str_replace( WP_CONTENT_DIR, end( $parts ), $path );
	}

	public function get_path() {
		return $this->path;
	}

	public function get_full_path() {
		$wp_content_dir = $this->fix_dir_separator( WP_CONTENT_DIR );
		$parts          = explode( DIRECTORY_SEPARATOR, $wp_content_dir );

		return str_replace( end( $parts ), $wp_content_dir, $this->path );
	}

	public function get_path_hash() {
		return md5( $this->path );
	}

	public function get_domain() {
		return $this->domain;
	}

	public function get_status() {
		return $this->status;
	}

	public function set_status( $status ) {
		$this->validate_status( $status );
		$this->status = $status;
	}

	public function get_imported_strings_count() {
		return $this->imported_strings_count;
	}

	public function set_imported_strings_count( $imported_strings_count ) {
		$this->imported_strings_count = (int) $imported_strings_count;
	}

	public function get_last_modified() {
		return $this->last_modified;
	}

	public function set_last_modified( $last_modified ) {
		$this->last_modified = (int) $last_modified;
	}

	public function __get( $name ) {
		if ( in_array( $name, array( 'path', 'domain', 'status', 'imported_strings_count', 'last_modified' ), true ) ) {
			return $this->$name;
		}
		if ( $name === 'path_md5' ) {
			return $this->get_path_hash();
		}

		return null;
	}

	public function get_file_locale() {
		return \WPML\Container\make( WPML_ST_Translations_File_Locale::class )->get( $this->get_path(), $this->get_domain() );
	}

	public function get_component_type() {
		return $this->component_type;
	}

	public function set_component_type( $component_type ) {
		$this->component_type = $component_type;
	}

	public function get_component_id() {
		return $this->component_id;
	}

	public function set_component_id( $component_id ) {
		$this->component_id = $component_id;
	}

	private function validate_status( $status ) {
		$allowed_statuses = array(
			self::NOT_IMPORTED,
			self::IMPORTED,
			self::PARTLY_IMPORTED,
			self::FINISHED,
			self::SKIPPED,
		);

		if ( ! in_array( $status, $allowed_statuses, true ) ) {
			throw new InvalidArgumentException( 'Status of MO file is invalid' );
		}
	}

	private function fix_dir_separator( $path ) {
		return ( '\\' === DIRECTORY_SEPARATOR ) ? str_replace( '/', '\\', $path ) : str_replace( '\\', '/', $path );
	}

	public function get_extension() {
		return pathinfo( $this->path, PATHINFO_EXTENSION );
	}
}
