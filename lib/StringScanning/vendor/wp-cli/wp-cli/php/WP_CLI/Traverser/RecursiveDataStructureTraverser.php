<?php

namespace WP_CLI\Traverser;

use UnexpectedValueException;
use WP_CLI\Exception\NonExistentKeyException;

class RecursiveDataStructureTraverser {

	protected $data;

	protected $key;

	protected $parent;

	public function __construct( &$data, $key = null, $parent_instance = null ) {
		$this->data   =& $data;
		$this->key    = $key;
		$this->parent = $parent_instance;
	}

	public function get( $key_path ) {
		return $this->traverse_to( (array) $key_path )->value();
	}

	public function value() {
		return $this->data;
	}

	public function update( $key_path, $value ) {
		$this->traverse_to( (array) $key_path )->set_value( $value );
	}

	public function set_value( $value ) {
		$this->data = $value;
	}

	public function delete( $key_path ) {
		$this->traverse_to( (array) $key_path )->unset_on_parent();
	}

	public function insert( $key_path, $value ) {
		try {
			$this->update( $key_path, $value );
		} catch ( NonExistentKeyException $exception ) {
			$exception->get_traverser()->create_key();
			$this->insert( $key_path, $value );
		}
	}

	public function unset_on_parent() {
		$this->parent->delete_by_key( $this->key );
	}

	public function delete_by_key( $key ) {
		if ( is_array( $this->data ) ) {
			unset( $this->data[ $key ] );
		} else {
			unset( $this->data->$key );
		}
	}

	public function traverse_to( array $key_path ) {
		$current = array_shift( $key_path );

		if ( null === $current ) {
			return $this;
		}

		if ( ! $this->exists( $current ) ) {
			$exception = new NonExistentKeyException( "No data exists for key \"{$current}\"" );
			$exception->set_traverser( new self( $this->data, $current, $this->parent ) );
			throw $exception;
		}

		foreach ( $this->data as $key => &$key_data ) {
			if ( $key === $current ) {
				$traverser = new self( $key_data, $key, $this );
				return $traverser->traverse_to( $key_path );
			}
		}
	}

	protected function create_key() {
		if ( is_array( $this->data ) ) {
			$this->data[ $this->key ] = null;
		} elseif ( is_object( $this->data ) ) {
			$this->data->{$this->key} = null;
		} else {
			$type = gettype( $this->data );
			throw new UnexpectedValueException(
				"Cannot create key \"{$this->key}\" on data type {$type}"
			);
		}
	}

	public function exists( $key ) {
		return ( is_array( $this->data ) && array_key_exists( $key, $this->data ) ) ||
			( is_object( $this->data ) && property_exists( $this->data, $key ) );
	}
}
