<?php

namespace WPML\ST\PackageTranslation;

class StringRowsCache {

	private static $rows_by_context = array();

	private static $inserted = array();

	private static $batching = false;

	private static $mark_buffer = array();

	public static function register() {
		add_action( 'wpml_start_GB_register_strings', array( self::class, 'preload' ), 10, 2 );
		add_action( 'wpml_end_GB_register_strings', array( self::class, 'endBatch' ), 10, 2 );
	}

	public static function preload( $post, $package_data ) {
		global $wpdb;

		$context = self::context_from( $post, $package_data );
		if ( null === $context ) {
			return;
		}

		self::$batching = true;

		if ( isset( self::$rows_by_context[ $context ] ) ) {
			return;
		}

		self::$rows_by_context[ $context ] = array();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, context, name, value, string_package_id, location, type, title, status, gettext_context FROM {$wpdb->prefix}icl_strings WHERE context = %s",
				$context
			),
			ARRAY_A
		);

		foreach ( (array) $rows as $row ) {
			self::$rows_by_context[ $context ][ (string) $row['name'] ] = $row;
		}
	}

	public static function endBatch( $post = null, $package_data = null ) {
		self::$batching = false;
		self::flushMarks();
	}

	public static function isBatching() {
		return self::$batching;
	}

	public static function findIdByName( $context, $name ) {
		if ( ! isset( self::$rows_by_context[ $context ] ) ) {
			return null;
		}

		if ( isset( self::$rows_by_context[ $context ][ $name ] ) ) {
			return (int) self::$rows_by_context[ $context ][ $name ]['id'];
		}

		foreach ( self::$inserted as $entry ) {
			if ( $entry['context'] === $context && $entry['name'] === $name ) {
				return (int) $entry['id'];
			}
		}

		return 0;
	}

	public static function findRegistered( $domain, $name, $gettext_context ) {
		if ( ! isset( self::$rows_by_context[ $domain ] ) ) {
			return null;
		}

		$key = md5( $domain . $name . $gettext_context );

		foreach ( self::$rows_by_context[ $domain ] as $row ) {
			if ( md5( $row['context'] . $row['name'] . $row['gettext_context'] ) === $key ) {
				return array(
					'id'    => $row['id'],
					'value' => $row['value'],
				);
			}
		}

		foreach ( self::$inserted as $entry ) {
			if ( md5( $entry['context'] . $entry['name'] . $entry['gettext_context'] ) === $key ) {
				return array(
					'id'    => $entry['id'],
					'value' => $entry['value'],
				);
			}
		}

		return false;
	}

	public static function getRow( $string_id ) {
		foreach ( self::$rows_by_context as $rows ) {
			foreach ( $rows as $row ) {
				if ( (int) $row['id'] === (int) $string_id ) {
					return $row;
				}
			}
		}

		return null;
	}

	public static function hasId( $string_id ) {
		if ( null !== self::getRow( $string_id ) ) {
			return true;
		}

		foreach ( self::$inserted as $entry ) {
			if ( (int) $entry['id'] === (int) $string_id && isset( self::$rows_by_context[ $entry['context'] ] ) ) {
				return true;
			}
		}

		return null;
	}

	public static function isNewlyInserted( $string_id ) {
		foreach ( self::$inserted as $entry ) {
			if ( (int) $entry['id'] === (int) $string_id ) {
				return true;
			}
		}

		return false;
	}

	public static function noteInsert( $string_id, $context, $name, $value, $gettext_context = '' ) {
		if ( ! $string_id ) {
			return;
		}

		self::$inserted[ (int) $string_id ] = array(
			'id'             => (int) $string_id,
			'context'        => (string) $context,
			'name'           => (string) $name,
			'value'          => (string) $value,
			'gettext_context' => (string) $gettext_context,
		);
	}

	public static function bufferMark( $package_id, $string_id = null ) {
		$package_id = (int) $package_id;
		if ( ! isset( self::$mark_buffer[ $package_id ] ) ) {
			self::$mark_buffer[ $package_id ] = array(
				'ids'  => array(),
				'fire' => false,
			);
		}

		self::$mark_buffer[ $package_id ]['fire'] = true;
		if ( null !== $string_id ) {
			self::$mark_buffer[ $package_id ]['ids'][] = (int) $string_id;
		}
	}

	public static function flushMarks() {
		global $wpdb;

		foreach ( self::$mark_buffer as $package_id => $mark ) {
			if ( ! $mark['fire'] ) {
				continue;
			}

			$storage = new \WPML_ST_Package_Storage( (int) $package_id, $wpdb );
			$storage->flush_batched_needs_update_marks( array_unique( $mark['ids'] ) );
		}

		self::$mark_buffer = array();
	}

	private static function context_from( $post, $package_data ) {
		if ( is_array( $package_data ) && ! empty( $package_data['kind'] ) && isset( $package_data['name'] ) && class_exists( '\WPML_Package' ) ) {
			$package = new \WPML_Package( $package_data );

			return $package->get_string_context_from_package();
		}

		if ( $post instanceof \WP_Post ) {
			return 'gutenberg-' . $post->ID;
		}

		return null;
	}

	public static function noteFieldUpdate( $string_id, array $fields ) {
		foreach ( self::$rows_by_context as $context => $rows ) {
			foreach ( $rows as $name => $row ) {
				if ( (int) $row['id'] === (int) $string_id ) {
					self::$rows_by_context[ $context ][ $name ] = array_merge( $row, $fields );

					return;
				}
			}
		}
	}

	public static function reset() {
		self::$rows_by_context = array();
		self::$inserted        = array();
		self::$mark_buffer     = array();
		self::$batching        = false;
	}
}
