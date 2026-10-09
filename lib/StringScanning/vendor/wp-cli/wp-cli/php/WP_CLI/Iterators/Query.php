<?php

namespace WP_CLI\Iterators;

use Iterator;

class Query implements Iterator {

	private $chunk_size;
	private $query       = '';
	private $count_query = '';

	private $global_index     = 0;
	private $index_in_results = 0;
	private $results          = [];
	private $row_count        = 0;
	private $offset           = 0;
	private $db               = null;
	private $depleted         = false;

	public function __construct( $query, $chunk_size = 500 ) {
		$this->query = $query;

		$this->count_query = preg_replace( '/^.*? FROM /', 'SELECT COUNT(*) FROM ', $query, 1, $replacements );
		if ( 1 !== $replacements ) {
			$this->count_query = '';
		}

		$this->chunk_size = $chunk_size;

		$this->db = $GLOBALS['wpdb'];
	}

	private function adjust_offset_for_shrinking_result_set() {
		if ( empty( $this->count_query ) ) {
			return;
		}

		$row_count = $this->db->get_var( $this->count_query );

		if ( $row_count < $this->row_count ) {
			$this->offset -= $this->row_count - $row_count;
		}

		$this->row_count = $row_count;
	}

	private function load_items_from_db() {
		$this->adjust_offset_for_shrinking_result_set();

		$query         = $this->query . sprintf( ' LIMIT %d OFFSET %d', $this->chunk_size, $this->offset );
		$this->results = $this->db->get_results( $query );

		if ( ! $this->results ) {
			if ( $this->db->last_error ) {
				throw new Exception( 'Database error: ' . $this->db->last_error );
			}

			return false;
		}

		$this->offset += $this->chunk_size;
		return true;
	}

	#[\ReturnTypeWillChange]
	public function current() {
		return $this->results[ $this->index_in_results ];
	}

	#[\ReturnTypeWillChange]
	public function key() {
		return $this->global_index;
	}

	#[\ReturnTypeWillChange]
	public function next() {
		++$this->index_in_results;
		++$this->global_index;
	}

	#[\ReturnTypeWillChange]
	public function rewind() {
		$this->results          = [];
		$this->global_index     = 0;
		$this->index_in_results = 0;
		$this->offset           = 0;
		$this->depleted         = false;
	}

	#[\ReturnTypeWillChange]
	public function valid() {
		if ( $this->depleted ) {
			return false;
		}

		if ( ! isset( $this->results[ $this->index_in_results ] ) ) {
			$items_loaded = $this->load_items_from_db();

			if ( ! $items_loaded ) {
				$this->rewind();
				$this->depleted = true;
				return false;
			}

			$this->index_in_results = 0;
		}

		return true;
	}
}
