<?php

class WPML_ST_Translations_File_Dictionary_Storage_Table implements WPML_ST_Translations_File_Dictionary_Storage {
	private $wpdb;

	private $data;

	private $new_data = array();

	private $updated_data = array();

	public function __construct( wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	public function add_hooks() {
		add_action( 'shutdown', array( $this, 'persist' ), 11, 0 );
	}

	public function save( WPML_ST_Translations_File_Entry $file ) {
		$this->load_data();

		$is_new                          = ! isset( $this->data[ $file->get_path() ] );
		$this->data[ $file->get_path() ] = $file;

		if ( $is_new ) {
			$this->new_data[] = $file;
		} else {
			$this->updated_data[] = $file;
		}
	}

	public function persist() {
		$wpdb = $this->wpdb;

		foreach ( $this->new_data as $file ) {
			$wpdb->query(
				$wpdb->prepare(
					"INSERT IGNORE INTO {$wpdb->prefix}icl_mo_files_domains ( file_path, file_path_md5, domain, status, num_of_strings, last_modified, component_type, component_id ) VALUES ( %s, %s, %s, %s, %d, %d, %s, %s )",
					array(
						$file->get_path(),
						$file->get_path_hash(),
						$file->get_domain(),
						$file->get_status(),
						$file->get_imported_strings_count(),
						$file->get_last_modified(),
						$file->get_component_type(),
						$file->get_component_id(),
					)
				)
			);
		}

		foreach ( $this->updated_data as $file ) {
			$this->wpdb->update(
				$this->wpdb->prefix . 'icl_mo_files_domains',
				$this->file_to_array( $file ),
				array(
					'file_path_md5' => $file->get_path_hash(),
				),
				array( '%s', '%s', '%d', '%d' )
			);
		}
	}

	private function file_to_array( WPML_ST_Translations_File_Entry $file, array $data = array() ) {
		$data['domain']         = $file->get_domain();
		$data['status']         = $file->get_status();
		$data['num_of_strings'] = $file->get_imported_strings_count();
		$data['last_modified']  = $file->get_last_modified();

		return $data;
	}

	public function find( $path = null, $status = null ) {
		$this->load_data();

		if ( null !== $path ) {
			return isset( $this->data[ $path ] ) ? array( $this->data[ $path ] ) : array();
		}

		if ( null === $status ) {
			return array_values( $this->data );
		}

		if ( ! is_array( $status ) ) {
			$status = array( $status );
		}

		$result = array();
		foreach ( $this->data as $file ) {
			if ( in_array( $file->get_status(), $status, true ) ) {
				$result[] = $file;
			}
		}

		return $result;
	}

	public function is_path_handled( $path, $domain ) {
		$wpdb = $this->wpdb;

		$file = new WPML_ST_Translations_File_Entry( $path, $domain );

		$id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id
				FROM {$wpdb->prefix}icl_mo_files_domains
				WHERE file_path_md5 = %s
				LIMIT 1",
				$file->get_path_hash()
			)
		);

		return null !== $id;
	}

	private function load_data() {
		if ( null === $this->data ) {
			$wpdb       = $this->wpdb;
			$this->data = array();
			$rowset     = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}icl_mo_files_domains" );

			foreach ( $rowset as $row ) {
				$file = new WPML_ST_Translations_File_Entry( $row->file_path, $row->domain, $row->status );
				$file->set_imported_strings_count( $row->num_of_strings );
				$file->set_last_modified( $row->last_modified );
				$file->set_component_type( $row->component_type );
				$file->set_component_id( $row->component_id );

				$this->data[ $file->get_path() ] = $file;
			}
		}
	}

	public function reset() {
		$this->data = null;
	}

	public function findAllUniqueComponentIds( ?string $componentType = null, array $fileExtensions = [] ): array {
		$wpdb = $this->wpdb;

		if ( ! $fileExtensions ) {
			if ( null === $componentType ) {
				return $wpdb->get_col( "SELECT DISTINCT(component_id) FROM {$wpdb->prefix}icl_mo_files_domains" );
			}

			return $wpdb->get_col(
				$wpdb->prepare(
					"SELECT DISTINCT(component_id) FROM {$wpdb->prefix}icl_mo_files_domains WHERE component_type = %s",
					$componentType
				)
			);
		}

		$rows = null === $componentType
			? $wpdb->get_results( "SELECT component_id, file_path FROM {$wpdb->prefix}icl_mo_files_domains" )
			: $wpdb->get_results(
				$wpdb->prepare(
					"SELECT component_id, file_path FROM {$wpdb->prefix}icl_mo_files_domains WHERE component_type = %s",
					$componentType
				)
			);

		$extensions   = array_map( 'strtolower', array_map( 'strval', $fileExtensions ) );
		$componentIds = array();
		foreach ( $rows as $row ) {
			if ( in_array( strtolower( pathinfo( $row->file_path, PATHINFO_EXTENSION ) ), $extensions, true ) ) {
				$componentIds[ (string) $row->component_id ] = $row->component_id;
			}
		}

		return array_values( $componentIds );
	}
}
