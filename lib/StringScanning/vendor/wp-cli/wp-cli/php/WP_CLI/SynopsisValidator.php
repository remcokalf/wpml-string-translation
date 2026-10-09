<?php

namespace WP_CLI;

class SynopsisValidator {

	private $spec;

	public function __construct( $synopsis ) {
		$this->spec = SynopsisParser::parse( $synopsis );
	}

	public function get_unknown() {
		return array_column(
			$this->query_spec(
				[
					'type' => 'unknown',
				]
			),
			'token'
		);
	}

	public function enough_positionals( $args ) {
		$positional = $this->query_spec(
			[
				'type'     => 'positional',
				'optional' => false,
			]
		);

		return count( $args ) >= count( $positional );
	}

	public function unknown_positionals( $args ) {
		$positional_repeating = $this->query_spec(
			[
				'type'      => 'positional',
				'repeating' => true,
			]
		);

		if ( ! empty( $positional_repeating ) ) {
			return [];
		}

		$positional = $this->query_spec(
			[
				'type'      => 'positional',
				'repeating' => false,
			]
		);

		return array_slice( $args, count( $positional ) );
	}

	public function validate_assoc( $assoc_args ) {
		$assoc_spec = $this->query_spec(
			[
				'type' => 'assoc',
			]
		);

		$errors = [
			'fatal'   => [],
			'warning' => [],
		];

		$to_unset = [];

		foreach ( $assoc_spec as $param ) {
			$key = $param['name'];

			if ( ! isset( $assoc_args[ $key ] ) ) {
				if ( ! $param['optional'] ) {
					$errors['fatal'][ $key ] = "missing --$key parameter";
				}
			} elseif ( true === $assoc_args[ $key ] && ! $param['value']['optional'] ) {
					$error_type                    = ( ! $param['optional'] ) ? 'fatal' : 'warning';
					$errors[ $error_type ][ $key ] = "--$key parameter needs a value";

					$to_unset[] = $key;
			}
		}

		return [ $errors, $to_unset ];
	}

	public function unknown_assoc( $assoc_args ) {
		$generic = $this->query_spec(
			[
				'type' => 'generic',
			]
		);

		if ( count( $generic ) ) {
			return [];
		}

		$known_assoc = [];

		foreach ( $this->spec as $param ) {
			if ( in_array( $param['type'], [ 'assoc', 'flag' ], true ) ) {
				$known_assoc[] = $param['name'];
			}
		}

		return array_diff( array_keys( $assoc_args ), $known_assoc );
	}

	private function query_spec( $args, $operator = 'AND' ) {
		$operator = strtoupper( $operator );
		$count    = count( $args );
		$filtered = [];

		foreach ( $this->spec as $key => $to_match ) {
			$matched = 0;
			foreach ( $args as $m_key => $m_value ) {
				if ( array_key_exists( $m_key, $to_match ) && $m_value === $to_match[ $m_key ] ) {
					++$matched;
				}
			}

			if ( ( 'AND' === $operator && $matched === $count )
				|| ( 'OR' === $operator && $matched > 0 )
				|| ( 'NOT' === $operator && 0 === $matched ) ) {
					$filtered[ $key ] = $to_match;
			}
		}

		return $filtered;
	}
}
