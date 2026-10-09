<?php

require_once dirname( __FILE__ ) . '/wpml-admin-text-functionality.class.php';

class WPML_Admin_Text_Config_Sync extends WPML_Admin_Text_Functionality {

	private $configured_names = array();

	private $configured_id_names = array();

	private $is_collecting = false;

	public function start() {
		$this->configured_names    = array();
		$this->configured_id_names = array();
		$this->is_collecting       = true;
	}

	public function collect( array $configured_names, array $configured_id_names ) {
		if ( ! $this->is_collecting ) {
			return;
		}

		$this->configured_names    = self::merge_names( $this->configured_names, $configured_names );
		$this->configured_id_names = array_replace_recursive(
			$this->configured_id_names,
			$configured_id_names
		);
	}

	public function finish() {
		if ( ! $this->is_collecting ) {
			return;
		}

		$this->reconcile(
			self::TRANSLATABLE_NAMES_SETTING,
			self::CONFIGURED_NAMES_SETTING,
			$this->configured_names,
			true
		);
		$this->reconcile(
			self::TRANSLATABLE_ID_NAMES_SETTING,
			self::CONFIGURED_ID_NAMES_SETTING,
			$this->configured_id_names,
			false
		);

		$this->is_collecting = false;
	}

	public function is_path_configured( array $path ) {
		$configured_names = get_option( self::CONFIGURED_NAMES_SETTING, array() );
		$current          = is_array( $configured_names ) ? $configured_names : array();

		foreach ( $path as $key ) {
			if ( ! is_array( $current ) ) {
				return true;
			}

			if ( ! array_key_exists( $key, $current ) ) {
				return false;
			}

			$current = $current[ $key ];
		}

		return true;
	}

	public static function merge_names( array $left, array $right ) {
		foreach ( $right as $key => $value ) {
			if ( ! array_key_exists( $key, $left ) ) {
				$left[ $key ] = $value;
				continue;
			}

			if ( is_array( $left[ $key ] ) && is_array( $value ) ) {
				$left[ $key ] = self::merge_names( $left[ $key ], $value );
			} elseif ( ! is_array( $left[ $key ] ) || ! is_array( $value ) ) {
				$left[ $key ] = 1;
			}
		}

		return $left;
	}

	public static function subtract( array $tree, array $to_remove ) {
		foreach ( $to_remove as $key => $value ) {
			if ( ! array_key_exists( $key, $tree ) ) {
				continue;
			}

			if ( is_array( $value ) ) {
				if ( ! is_array( $tree[ $key ] ) ) {
					continue;
				}

				$tree[ $key ] = self::subtract( $tree[ $key ], $value );

				if ( array() === $tree[ $key ] ) {
					unset( $tree[ $key ] );
				}
			} else {
				unset( $tree[ $key ] );
			}
		}

		return $tree;
	}

	private function reconcile( $setting_name, $configured_setting_name, array $configured_now, $is_names_tree ) {
		$setting            = get_option( $setting_name, array() );
		$setting            = is_array( $setting ) ? $setting : array();
		$configured_before  = get_option( $configured_setting_name, null );
		$has_previous_state = is_array( $configured_before );
		$manual_setting     = $has_previous_state
			? self::subtract( $setting, $configured_before )
			: $setting;

		$next_setting = $is_names_tree
			? self::merge_names( $manual_setting, $configured_now )
			: array_replace_recursive( $manual_setting, $configured_now );

		if ( $next_setting !== $setting ) {
			update_option( $setting_name, $next_setting, 'no' );
		}

		if ( ! $has_previous_state || $configured_now !== $configured_before ) {
			update_option( $configured_setting_name, $configured_now, 'no' );
		}
	}
}
