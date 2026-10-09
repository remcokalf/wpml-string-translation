<?php

namespace WP_CLI\Iterators;

class Table extends Query {

	public function __construct( $args = [] ) {
		$defaults = [
			'fields'     => '*',
			'where'      => [],
			'append'     => '',
			'table'      => null,
			'chunk_size' => 500,
		];
		$table    = $args['table'];
		$args     = array_merge( $defaults, $args );

		$fields     = self::build_fields( $args['fields'] );
		$conditions = self::build_where_conditions( $args['where'] );
		$where_sql  = $conditions ? " WHERE $conditions" : '';
		$query      = "SELECT $fields FROM `$table` $where_sql {$args['append']}";

		parent::__construct( $query, $args['chunk_size'] );
	}

	private static function build_fields( $fields ) {
		if ( '*' === $fields ) {
			return $fields;
		}

		return implode(
			', ',
			array_map(
				function ( $v ) {
					return "`$v`";
				},
				$fields
			)
		);
	}

	private static function build_where_conditions( $where ) {
		global $wpdb;
		if ( is_array( $where ) ) {
			$conditions = [];
			foreach ( $where as $key => $value ) {
				if ( is_array( $value ) ) {
					$conditions[] = $key . ' IN (' . esc_sql( implode( ',', $value ) ) . ')';
				} elseif ( is_numeric( $key ) ) {
					$conditions[] = $value;
				} else {
					$conditions[] = $key . $wpdb->prepare( ' = %s', $value );
				}
			}
			$where = implode( ' AND ', $conditions );
		}
		return $where;
	}
}
