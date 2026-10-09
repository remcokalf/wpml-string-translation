<?php

use WPML\ST\StringValue;

class WPML_ST_Bulk_Strings_Insert_Exception extends Exception {

}

class WPML_ST_Bulk_Strings_Insert {
	private $wpdb;

	private $chunk_size = 1000;

	public function __construct( wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	public function set_chunk_size( $chunk_size ) {
		$this->chunk_size = $chunk_size;
	}


	public function insert_strings( array $strings ) {
		foreach ( array_chunk( $strings, $this->chunk_size ) as $chunk ) {
			$wpdb     = $this->wpdb;
			$has_text = StringValue::isReady();
			$rows     = array();
			$args     = array();
			foreach ( $chunk as $string ) {
				$rows[] = $this->build_string_row( $string, $has_text, $args );
			}

			$wpdb->suppress_errors = true;
			if ( $has_text ) {
				$wpdb->query(
					$wpdb->prepare(
						"INSERT IGNORE INTO {$wpdb->prefix}icl_strings "
						. '(`language`, `context`, `gettext_context`, `domain_name_context_md5`, `name`, `value`, `status`, `has_text`) VALUES '
						. implode( ',', array_fill( 0, count( $rows ), '(%s, %s, %s, %s, %s, %s, %d, %d)' ) ),
						$args[0],
						$args[1],
						$args[2],
						$args[3],
						$args[4],
						$args[5],
						$args[6],
						...array_slice( $args, 7 )
					)
				);
			} else {
				$wpdb->query(
					$wpdb->prepare(
						"INSERT IGNORE INTO {$wpdb->prefix}icl_strings "
						. '(`language`, `context`, `gettext_context`, `domain_name_context_md5`, `name`, `value`, `status`) VALUES '
						. implode( ',', array_fill( 0, count( $rows ), '(%s, %s, %s, %s, %s, %s, %d)' ) ),
						$args[0],
						$args[1],
						$args[2],
						$args[3],
						$args[4],
						$args[5],
						...array_slice( $args, 6 )
					)
				);
			}
			$wpdb->suppress_errors = false;
			if ( $wpdb->last_error ) {
				throw new WPML_ST_Bulk_Strings_Insert_Exception( 'Deadlock with bulk insert' );
			}
		}
	}

	public function insert_string_translations( array $translations ) {
		foreach ( array_chunk( $translations, $this->chunk_size ) as $chunk ) {
			$wpdb = $this->wpdb;
			$rows = array();
			$args = array();
			foreach ( $chunk as $translation ) {
				$rows[] = $this->build_translation_row( $translation, $args );
			}

			$wpdb->suppress_errors = true;
			$wpdb->query(
				$wpdb->prepare(
					"INSERT IGNORE INTO {$wpdb->prefix}icl_string_translations "
						. '(`string_id`, `language`, `status`, `mo_string`) VALUES '
						. implode( ',', array_fill( 0, count( $rows ), '(%d, %s, %d, %s)' ) ) . ' '
						. 'ON DUPLICATE KEY UPDATE `mo_string`=VALUES(`mo_string`)',
					$args[0],
					$args[1],
					$args[2],
					...array_slice( $args, 3 )
				)
			);
			$wpdb->suppress_errors = false;
			if ( $wpdb->last_error ) {
				throw new WPML_ST_Bulk_Strings_Insert_Exception( 'Deadlock with bulk insert' );
			}
		}
	}

	private function build_string_row( WPML_ST_Models_String $string, $has_text, array &$args ) {
		array_push(
			$args,
			(string) $string->get_language(),
			(string) $string->get_domain(),
			(string) $string->get_context(),
			(string) $string->get_domain_name_context_md5(),
			(string) $string->get_name(),
			(string) $string->get_value(),
			(int) $string->get_status()
		);
		if ( $has_text ) {
			$args[] = (int) StringValue::hasTextFlag( $string->get_value() );
			return '(%s, %s, %s, %s, %s, %s, %d, %d)';
		}

		return '(%s, %s, %s, %s, %s, %s, %d)';
	}

	private function build_translation_row( WPML_ST_Models_String_Translation $translation, array &$args ) {
		array_push(
			$args,
			(int) $translation->get_string_id(),
			(string) $translation->get_language(),
			(int) $translation->get_status(),
			(string) $translation->get_mo_string()
		);

		return '(%d, %s, %d, %s)';
	}
}
