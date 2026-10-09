<?php


use Mustangostang\Spyc;
use WP_CLI\ExitException;
use WP_CLI\Utils;

class CLI_Alias_Command extends WP_CLI_Command {

	public function list_( $args, $assoc_args ) {
		WP_CLI::print_value( WP_CLI::get_runner()->aliases, $assoc_args );
	}

	public function get( $args, $assoc_args ) {
		list( $alias ) = $args;

		$aliases = WP_CLI::get_runner()->aliases;

		if ( empty( $aliases[ $alias ] ) ) {
			WP_CLI::error( "No alias found with key '{$alias}'." );
		}

		foreach ( $aliases[ $alias ] as $key => $value ) {
			WP_CLI::log( "{$key}: {$value}" );
		}
	}

	public function add( $args, $assoc_args ) {

		$config = ( ! empty( $assoc_args['config'] ) ? $assoc_args['config'] : 'global' );

		list( $config_path, $aliases ) = $this->get_aliases_data( $config, '', true );

		$this->validate_config_file( $config_path );

		$alias    = $args[0];
		$grouping = Utils\get_flag_value( $assoc_args, 'grouping' );

		$this->validate_input( $assoc_args, $grouping );

		if ( isset( $aliases[ $alias ] ) ) {
			WP_CLI::error( "Key '{$alias}' exists already." );
		}

		if ( null === $grouping ) {
			$aliases = $this->build_aliases( $aliases, $alias, $assoc_args, false );
		} else {
			$aliases = $this->build_aliases( $aliases, $alias, $assoc_args, true, $grouping );
		}

		$this->process_aliases( $aliases, $alias, $config_path, 'Added' );
	}

	public function delete( $args, $assoc_args ) {

		list( $alias ) = $args;

		$config = ( ! empty( $assoc_args['config'] ) ? $assoc_args['config'] : '' );

		list( $config_path, $aliases ) = $this->get_aliases_data( $config, $alias );

		$this->validate_config_file( $config_path );

		if ( empty( $aliases[ $alias ] ) ) {
			WP_CLI::error( "No alias found with key '{$alias}'." );
		}

		unset( $aliases[ $alias ] );
		$this->process_aliases( $aliases, $alias, $config_path, 'Deleted' );
	}

	public function update( $args, $assoc_args ) {

		$config   = ( ! empty( $assoc_args['config'] ) ? $assoc_args['config'] : '' );
		$alias    = $args[0];
		$grouping = Utils\get_flag_value( $assoc_args, 'grouping' );

		list( $config_path, $aliases ) = $this->get_aliases_data( $config, $alias, true );

		$this->validate_config_file( $config_path );

		$this->validate_input( $assoc_args, $grouping );

		if ( empty( $aliases[ $alias ] ) ) {
			WP_CLI::error( "No alias found with key '{$alias}'." );
		}

		if ( null === $grouping ) {
			$aliases = $this->build_aliases( $aliases, $alias, $assoc_args, false, '', true );
		} else {
			$aliases = $this->build_aliases( $aliases, $alias, $assoc_args, true, $grouping, true );
		}

		$this->process_aliases( $aliases, $alias, $config_path, 'Updated' );
	}

	public function is_group( $args, $assoc_args = array() ) {
		$alias = $args[0];

		$aliases = WP_CLI::get_runner()->aliases;

		if ( empty( $aliases[ $alias ] ) ) {
			WP_CLI::error( "No alias found with key '{$alias}'." );
		}


		$first_item       = $aliases[ $alias ];
		$first_item_key   = key( $first_item );
		$first_item_value = $first_item[ $first_item_key ];

		if ( is_numeric( $first_item_key ) && substr( $first_item_value, 0, 1 ) === '@' ) {
			WP_CLI::halt( 0 );
		}
		WP_CLI::halt( 1 );
	}

	private function get_aliases_data( $config, $alias, $create_config_file = false ) {

		$global_config_path = WP_CLI::get_runner()->get_global_config_path( $create_config_file );
		$global_aliases     = Spyc::YAMLLoad( $global_config_path );

		$project_config_path = WP_CLI::get_runner()->get_project_config_path();
		$project_aliases     = Spyc::YAMLLoad( $project_config_path );

		if ( 'global' === $config ) {
			$config_path = $global_config_path;
			$aliases     = $global_aliases;
		} elseif ( 'project' === $config ) {
			$config_path = $project_config_path;
			$aliases     = $project_aliases;
		} else {

			$is_global_alias  = array_key_exists( $alias, $global_aliases );
			$is_project_alias = array_key_exists( $alias, $project_aliases );

			if ( $is_global_alias && $is_project_alias ) {
				WP_CLI::error( "Key '{$alias}' found in more than one path. Please pass --config param." );
			} elseif ( $is_global_alias ) {
				$config_path = $global_config_path;
				$aliases     = $global_aliases;
			} else {
				$config_path = $project_config_path;
				$aliases     = $project_aliases;
			}
		}

		return [ $config_path, $aliases ];
	}

	private function validate_config_file( $config_path ) {
		if ( ! file_exists( $config_path ) || ! is_writable( $config_path ) ) {
			WP_CLI::error( "Config file does not exist: {$config_path}" );
		}
	}

	private function build_aliases( $aliases, $alias, $assoc_args, $is_grouping, $grouping = '', $is_update = false ) {
		$alias = $this->normalize_alias( $alias );

		if ( $is_grouping ) {
			$valid_assoc_args = [ 'config', 'grouping' ];
			$invalid_args     = array_diff( array_keys( $assoc_args ), $valid_assoc_args );

			if ( ! empty( $invalid_args ) ) {
				$args_info = implode( ',', $invalid_args );
				WP_CLI::error( "--grouping argument works alone. Found invalid arg(s) '$args_info'." );
			}
		}

		if ( $is_update ) {
			$this->validate_alias_type( $aliases, $alias, $assoc_args, $grouping );
		}

		if ( ! $is_grouping ) {
			foreach ( $assoc_args as $key => $value ) {
				if ( strpos( $key, 'set-' ) !== false ) {
					$alias_key_info = explode( '-', $key );
					$alias_key      = empty( $alias_key_info[1] ) ? '' : $alias_key_info[1];
					if ( ! empty( $alias_key ) && ! empty( $value ) ) {
						$aliases[ $alias ][ $alias_key ] = $value;
					}
				}
			}
		} elseif ( ! empty( $grouping ) ) {

				$group_alias_list  = explode( ',', $grouping );
				$group_alias       = array_map(
					function ( $current_alias ) {
						return '@' . ltrim( $current_alias, '@' );
					},
					$group_alias_list
				);
				$aliases[ $alias ] = $group_alias;
		}

		return $aliases;
	}

	private function validate_input( $assoc_args, $grouping ) {
		$arg_match = preg_grep( '/^set-(\w+)/i', array_keys( $assoc_args ) );

		if ( empty( $grouping ) && empty( $arg_match ) ) {
			WP_CLI::error( 'No valid arguments passed.' );
		}

		$assoc_arg_values = array_filter( array_intersect_key( $assoc_args, array_flip( $arg_match ) ) );

		if ( empty( $grouping ) && empty( $assoc_arg_values ) ) {
			WP_CLI::error( 'No value passed to arguments.' );
		}
	}

	private function validate_alias_type( $aliases, $alias, $assoc_args, $grouping ) {

		$alias_data = $aliases[ $alias ];

		$group_aliases_match = preg_grep( '/^@(\w+)/i', $alias_data );
		$arg_match           = preg_grep( '/^set-(\w+)/i', array_keys( $assoc_args ) );

		if ( ! empty( $group_aliases_match ) && ! empty( $arg_match ) ) {
			WP_CLI::error( 'Trying to update group alias with invalid arguments.' );
		} elseif ( empty( $group_aliases_match ) && ! empty( $grouping ) ) {
			WP_CLI::error( 'Trying to update simple alias with invalid --grouping argument.' );
		}
	}

	private function process_aliases( $aliases, $alias, $config_path, $operation = '' ) {
		$alias = $this->normalize_alias( $alias );

		$yaml_data = Spyc::YAMLDump( $aliases );

		if ( file_put_contents( $config_path, $yaml_data ) ) {
			WP_CLI::success( "$operation '{$alias}' alias." );
		}
	}

	private function normalize_alias( $alias ) {
		if ( strpos( $alias, '@' ) !== 0 ) {
			$alias = '@' . ltrim( $alias, '@' );
		}

		return $alias;
	}
}
