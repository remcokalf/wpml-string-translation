<?php

use WPML\Element\API\Languages;
use WPML\FP\Fns;

class WPML_ST_Translations_File_Registration {

	const LOCALE_PLACEHOLDER = 'LOCALE_PLACEHOLDER';

	const PATH_PATTERN_SEARCH_MO  = '#(-)?([a-z]+)([_A-Z]*)\.mo$#i';
	const PATH_PATTERN_REPLACE_MO = '${1}LOCALE_PLACEHOLDER.mo';

	const PATH_PATTERN_SEARCH_JSON  = '#(DOMAIN_PLACEHOLDER)([a-z]+)([_A-Z]*)(-[-_a-z0-9]+)\.json$#i';
	const PATH_PATTERN_REPLACE_JSON = '${1}LOCALE_PLACEHOLDER${4}.json';

	private $file_dictionary;

	private $wpml_file;

	private $components_find;

	private $active_languages;

	private $cache = array();

	private $getWPLocale;

	private $is_on_frontend_page;

	private $is_on_admin_page;

	private $is_on_theme_and_localization_page;

	public function __construct(
		WPML_ST_Translations_File_Dictionary $file_dictionary,
		WPML_File $wpml_file,
		WPML_ST_Translations_File_Component_Details $components_find,
		array $active_languages,
		$is_on_frontend_page = false,
		$is_on_admin_page = false,
		$is_on_theme_and_localization_page = false
	) {
		$this->file_dictionary                   = $file_dictionary;
		$this->wpml_file                         = $wpml_file;
		$this->components_find                   = $components_find;
		$this->active_languages                  = $active_languages;
		$this->getWPLocale                       = Fns::memorize( Languages::getWPLocale() );
		$this->is_on_frontend_page               = $is_on_frontend_page;
		$this->is_on_admin_page                  = $is_on_admin_page;
		$this->is_on_theme_and_localization_page = $is_on_theme_and_localization_page;
	}

	public function add_hooks() {
		if ( $this->is_on_frontend_page || $this->is_on_theme_and_localization_page ) {
			add_filter( 'override_load_textdomain', array( $this, 'cached_save_mo_file_info' ), 11, 3 );
			add_filter( 'pre_load_script_translations', array( $this, 'add_json_translations_to_import_queue' ), 10, 4 );
		}

		add_filter( 'load_script_translation_file', array( $this, 'load_script_translation_file' ), PHP_INT_MAX, 3 );
	}

	public function cached_save_mo_file_info( $override, $domain, $mo_file_path ) {
		if ( ! isset( $this->cache[ $mo_file_path ] ) ) {
			$this->cache[ $mo_file_path ] = $this->save_file_info( $domain, $domain, $mo_file_path );
		}

		return $override;
	}

	public function load_script_translation_file( $file, $handle, $original_domain ) {
		$this->add_json_translations_to_import_queue( false, $file, $handle, $original_domain );

		return $file;
	}

	public function add_json_translations_to_import_queue( $translations, $file, $handle, $original_domain ) {
		if ( $file && ! isset( $this->cache[ $file ] ) ) {
			$registration_domain  = WPML_ST_JED_Domain::get( $original_domain, $handle );
			$this->cache[ $file ] = $this->save_file_info( $original_domain, $registration_domain, $file );
		}

		return $translations;
	}

	private function save_file_info( $original_domain, $registration_domain, $file_path ) {
		try {
			$file_path_pattern = $this->get_file_path_pattern( $file_path, $original_domain );
		} catch ( RuntimeException $e ) {
			return true;
		}

		try {
			foreach ( $this->active_languages as $lang_data ) {
				$file_path_in_lang = str_replace(
					self::LOCALE_PLACEHOLDER,
					$lang_data['default_locale'],
					(string) $file_path_pattern
				);
				$this->register_single_file( $registration_domain, $file_path_in_lang );
			}
		} catch ( \Throwable $e ) {
			error_log( 'WPML String Translation: failed to register the translation file "' . $file_path . '": ' . $e->getMessage() );
		}

		return true;
	}

	private function get_file_path_pattern( $file_path, $original_domain ) {
		$pathinfo  = pathinfo( $file_path );
		$file_type = isset( $pathinfo['extension'] ) ? $pathinfo['extension'] : null;

		switch ( $file_type ) {
			case 'mo':
				return preg_replace( self::PATH_PATTERN_SEARCH_MO, self::PATH_PATTERN_REPLACE_MO, $file_path );

			case 'json':
				$domain_replace = 'default' === $original_domain ? '' : $original_domain . '-';
				$search_pattern = str_replace( 'DOMAIN_PLACEHOLDER', preg_quote( $domain_replace, '#' ), self::PATH_PATTERN_SEARCH_JSON );
				return preg_replace( $search_pattern, self::PATH_PATTERN_REPLACE_JSON, $file_path );
		}

		throw new RuntimeException( 'The "' . $file_type . '" file type is not supported for registration' );
	}

	private function register_single_file( $registration_domain, $file_path ) {
		if (
			! $this->wpml_file->file_exists( $file_path ) ||
			$this->isGeneratedFile( $file_path )
		) {
			return;
		}

		$storage_path  = $this->get_stable_storage_path( $file_path );
		$relative_path = $this->wpml_file->get_relative_path( $storage_path );
		$last_modified = $this->wpml_file->get_file_modified_timestamp( $file_path );
		$file          = $this->file_dictionary->find_file_info_by_path( $relative_path );

		if ( ! $file ) {
			if ( ! $this->components_find->is_component_active( $file_path ) ) {
				return;
			}

			$file = new WPML_ST_Translations_File_Entry( $relative_path, $registration_domain );
			$file->set_last_modified( $last_modified );

			list( $component_type, $component_id ) = $this->components_find->find_details( $file_path );
			$file->set_component_type( $component_type );
			$file->set_component_id( $component_id );

			$this->file_dictionary->save( $file );
		} elseif ( $file->get_last_modified() !== $last_modified ) {
			$file->set_status( WPML_ST_Translations_File_Entry::NOT_IMPORTED );
			$file->set_last_modified( $last_modified );
			$file->set_imported_strings_count( 0 );

			$this->file_dictionary->save( $file );
		}
	}

	private function get_stable_storage_path( $file_path ) {
		$component = $this->components_find->find_details( $file_path );
		if ( ! is_array( $component ) || 'plugin' !== $component[0] || empty( $component[1] ) ) {
			return $file_path;
		}

		$logical_path = WPML_ST_Path_Confinement::resolve_registered_plugin_logical_path( $file_path, $component[1] );

		return false !== $logical_path ? $logical_path : $file_path;
	}

	private function isGeneratedFile( $path ) {
		return strpos(
			$this->wpml_file->fix_dir_separator( $path ),
			$this->wpml_file->fix_dir_separator( WPML\ST\TranslationFile\Manager::getSubdir() )
		) === 0;
	}

}
