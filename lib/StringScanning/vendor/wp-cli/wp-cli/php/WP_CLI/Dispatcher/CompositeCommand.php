<?php

namespace WP_CLI\Dispatcher;

use WP_CLI;
use WP_CLI\DocParser;
use WP_CLI\Utils;

class CompositeCommand {

	protected $name;
	protected $shortdesc;
	protected $longdesc;
	protected $synopsis;
	protected $hook;
	protected $docparser;

	protected $parent;
	protected $subcommands = [];

	public function __construct( $parent, $name, $docparser ) {
		$this->parent = $parent;

		$this->name = $name;

		$this->shortdesc = $docparser->get_shortdesc();
		$this->longdesc  = $docparser->get_longdesc();
		$this->docparser = $docparser;
		$this->hook      = $parent->get_hook();

		$when_to_invoke = $docparser->get_tag( 'when' );
		if ( $when_to_invoke ) {
			$this->hook = $when_to_invoke;
			WP_CLI::get_runner()->register_early_invoke( $when_to_invoke, $this );
		}
	}

	public function get_parent() {
		return $this->parent;
	}

	public function add_subcommand( $name, $command, $override = true ) {
		if ( $override || ! array_key_exists( $name, $this->subcommands ) ) {
			$this->subcommands[ $name ] = $command;
		}
	}

	public function remove_subcommand( $name ) {
		if ( isset( $this->subcommands[ $name ] ) ) {
			unset( $this->subcommands[ $name ] );
		}
	}


	public function can_have_subcommands() {
		return true;
	}

	public function get_subcommands() {
		ksort( $this->subcommands );

		return $this->subcommands;
	}

	public function get_name() {
		return $this->name;
	}

	public function get_shortdesc() {
		return $this->shortdesc;
	}

	public function get_hook() {
		return $this->hook;
	}

	public function set_shortdesc( $shortdesc ) {
		$this->shortdesc = Utils\normalize_eols( $shortdesc );
	}

	public function get_longdesc() {
		return $this->longdesc . $this->get_global_params();
	}

	public function set_longdesc( $longdesc ) {
		$this->longdesc = Utils\normalize_eols( $longdesc );
	}

	public function get_synopsis() {
		return '<command>';
	}

	public function get_usage( $prefix ) {
		return sprintf(
			'%s%s %s',
			$prefix,
			implode( ' ', get_path( $this ) ),
			$this->get_synopsis()
		);
	}

	public function show_usage() {
		$methods = $this->get_subcommands();

		$i = 0;

		foreach ( $methods as $subcommand ) {
			$prefix = ( 0 === $i ) ? 'usage: ' : '   or: ';
			++$i;

			if ( WP_CLI::get_runner()->is_command_disabled( $subcommand ) ) {
				continue;
			}

			WP_CLI::line( $subcommand->get_usage( $prefix ) );
		}

		$cmd_name = implode( ' ', array_slice( get_path( $this ), 1 ) );

		WP_CLI::line();
		WP_CLI::line( "See 'wp help $cmd_name <command>' for more information on a specific command." );
	}

	public function invoke( $args, $assoc_args, $extra_args ) {
		$this->show_usage();
	}

	public function find_subcommand( &$args ) {
		$name = array_shift( $args );

		$subcommands = $this->get_subcommands();

		if ( ! isset( $subcommands[ $name ] ) ) {
			$aliases = self::get_aliases( $subcommands );

			if ( isset( $aliases[ $name ] ) ) {
				$name = $aliases[ $name ];
			}
		}

		if ( ! isset( $subcommands[ $name ] ) ) {
			return false;
		}

		return $subcommands[ $name ];
	}

	private static function get_aliases( $subcommands ) {
		$aliases = [];

		foreach ( $subcommands as $name => $subcommand ) {
			$alias = $subcommand->get_alias();
			if ( $alias ) {
				$aliases[ $alias ] = $name;
			}
		}

		return $aliases;
	}

	public function get_alias() {
		return false;
	}

	protected function get_global_params( $root_command = false ) {
		$binding                 = [];
		$binding['root_command'] = $root_command;

		if ( ! $this->can_have_subcommands() || ( is_object( $this->parent ) && get_class( $this->parent ) === 'WP_CLI\Dispatcher\CompositeCommand' ) ) {
			$binding['is_subcommand'] = true;
		}

		foreach ( WP_CLI::get_configurator()->get_spec() as $key => $details ) {
			if ( false === $details['runtime'] ) {
				continue;
			}

			if ( isset( $details['deprecated'] ) ) {
				continue;
			}

			if ( isset( $details['hidden'] ) ) {
				continue;
			}

			if ( true === $details['runtime'] ) {
				$synopsis = "--[no-]$key";
			} else {
				$synopsis = "--$key" . $details['runtime'];
			}

			if ( 'true' !== getenv( 'WP_CLI_SUPPRESS_GLOBAL_PARAMS' ) ) {
				$binding['parameters'][]   = [
					'synopsis' => $synopsis,
					'desc'     => $details['desc'],
				];
				$binding['has_parameters'] = true;
			}
		}

		if ( $this->get_subcommands() ) {
			$binding['has_subcommands'] = true;
		}

		return Utils\mustache_render( 'man-params.mustache', $binding );
	}
}
