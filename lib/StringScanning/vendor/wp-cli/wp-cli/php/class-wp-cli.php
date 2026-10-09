<?php

use cli\Colors;
use Mustangostang\Spyc;
use WP_CLI\Configurator;
use WP_CLI\Dispatcher;
use WP_CLI\Dispatcher\CommandAddition;
use WP_CLI\Dispatcher\CommandFactory;
use WP_CLI\Dispatcher\CommandNamespace;
use WP_CLI\Dispatcher\CompositeCommand;
use WP_CLI\Dispatcher\RootCommand;
use WP_CLI\DocParser;
use WP_CLI\ExitException;
use WP_CLI\FileCache;
use WP_CLI\Loggers\Execution;
use WP_CLI\Process;
use WP_CLI\ProcessRun;
use WP_CLI\Runner;
use WP_CLI\SynopsisParser;
use WP_CLI\Utils;
use WP_CLI\WpHttpCacheManager;

class WP_CLI {

	private static $logger;

	private static $hooks = [];

	private static $hooks_passed = [];

	private static $capture_exit = false;

	private static $deferred_additions = [];

	public static function set_logger( $logger ) {
		self::$logger = $logger;
	}

	public static function get_logger() {
		return self::$logger;
	}

	public static function get_configurator() {
		static $configurator;

		if ( ! $configurator ) {
			$configurator = new Configurator( WP_CLI_ROOT . '/php/config-spec.php' );
		}

		return $configurator;
	}

	public static function get_root_command() {
		static $root;

		if ( ! $root ) {
			$root = new RootCommand();
		}

		return $root;
	}

	public static function get_runner() {
		static $runner;

		if ( ! $runner ) {
			$runner = new Runner();
		}

		return $runner;
	}

	public static function get_cache() {
		static $cache;

		if ( ! $cache ) {
			$dir      = Utils\get_cache_dir();
			$ttl      = getenv( 'WP_CLI_CACHE_EXPIRY' ) ? : 15552000;
			$max_size = getenv( 'WP_CLI_CACHE_MAX_SIZE' ) ? : 314572800;
			$cache = new FileCache( $dir, $ttl, $max_size );

			if ( 0 === mt_rand( 0, 50 ) ) {
				register_shutdown_function(
					function () use ( $cache ) {
						$cache->clean();
					}
				);
			}
		}

		return $cache;
	}

	public static function set_url( $url ) {
		self::debug( 'Set URL: ' . $url, 'bootstrap' );
		$url_parts = Utils\parse_url( $url );
		self::set_url_params( $url_parts );
	}

	private static function set_url_params( $url_parts ) {
		$f = function ( $key ) use ( $url_parts ) {
			return Utils\get_flag_value( $url_parts, $key, '' );
		};

		if ( isset( $url_parts['host'] ) ) {
			if ( isset( $url_parts['scheme'] ) && 'https' === strtolower( $url_parts['scheme'] ) ) {
				$_SERVER['HTTPS'] = 'on';
			}

			$_SERVER['HTTP_HOST'] = $url_parts['host'];
			if ( isset( $url_parts['port'] ) ) {
				$_SERVER['HTTP_HOST'] .= ':' . $url_parts['port'];
			}

			$_SERVER['SERVER_NAME'] = $url_parts['host'];
		}

		$_SERVER['REQUEST_URI']  = $f( 'path' ) . ( isset( $url_parts['query'] ) ? '?' . $url_parts['query'] : '' );
		$_SERVER['SERVER_PORT']  = Utils\get_flag_value( $url_parts, 'port', '80' );
		$_SERVER['QUERY_STRING'] = $f( 'query' );
	}

	public static function get_http_cache_manager() {
		static $http_cacher;

		if ( ! $http_cacher ) {
			$http_cacher = new WpHttpCacheManager( self::get_cache() );
		}

		return $http_cacher;
	}

	public static function colorize( $string ) {
		return Colors::colorize( $string, self::get_runner()->in_color() );
	}

	public static function add_hook( $when, $callback ) {
		if ( array_key_exists( $when, self::$hooks_passed ) ) {
			self::debug(
				sprintf(
					'Immediately invoking on passed hook "%s": %s',
					$when,
					Utils\describe_callable( $callback )
				),
				'hooks'
			);
			call_user_func_array( $callback, (array) self::$hooks_passed[ $when ] );
		}

		self::$hooks[ $when ][] = $callback;
	}

	public static function do_hook( $when, ...$args ) {
		self::$hooks_passed[ $when ] = $args;

		$has_args = count( $args ) > 0;

		if ( ! isset( self::$hooks[ $when ] ) ) {
			if ( $has_args ) {
				return $args[0];
			}

			return null;
		}

		self::debug(
			sprintf(
				'Processing hook "%s" with %d callbacks',
				$when,
				count( self::$hooks[ $when ] )
			),
			'hooks'
		);

		foreach ( self::$hooks[ $when ] as $callback ) {
			self::debug(
				sprintf(
					'On hook "%s": %s',
					$when,
					Utils\describe_callable( $callback )
				),
				'hooks'
			);

			if ( $has_args ) {
				$return_value = $callback( ...$args );
				if ( isset( $return_value ) ) {
					$args[0] = $return_value;
				}
			} else {
				$callback();
			}
		}

		if ( $has_args ) {
			return $args[0];
		}

		return null;
	}

	public static function add_wp_hook( $tag, $function_to_add, $priority = 10, $accepted_args = 1 ) {
		global $wp_filter, $merged_filters;

		if ( function_exists( 'add_filter' ) ) {
			add_filter( $tag, $function_to_add, $priority, $accepted_args );
		} else {
			$idx = self::wp_hook_build_unique_id( $tag, $function_to_add, $priority );

			$wp_filter[ $tag ][ $priority ][ $idx ] = [
				'function'      => $function_to_add,
				'accepted_args' => $accepted_args,
			];
			unset( $merged_filters[ $tag ] );
		}

		return true;
	}

	private static function wp_hook_build_unique_id( $tag, $function, $priority ) {
		global $wp_filter;
		static $filter_id_count = 0;

		if ( is_string( $function ) ) {
			return $function;
		}

		if ( is_object( $function ) ) {
			$function = [ $function, '' ];
		} else {
			$function = (array) $function;
		}

		if ( is_object( $function[0] ) ) {
			if ( function_exists( 'spl_object_hash' ) ) {
				return spl_object_hash( $function[0] ) . $function[1];
			}

			$obj_idx = get_class( $function[0] ) . $function[1];
			if ( ! isset( $function[0]->wp_filter_id ) ) {
				if ( false === $priority ) {
					return false;
				}
				$obj_idx                  .= isset( $wp_filter[ $tag ][ $priority ] ) ? count( (array) $wp_filter[ $tag ][ $priority ] ) : $filter_id_count;
				$function[0]->wp_filter_id = $filter_id_count;
				++$filter_id_count;
			} else {
				$obj_idx .= $function[0]->wp_filter_id;
			}

			return $obj_idx;
		}

		if ( is_string( $function[0] ) ) {
			return $function[0] . '::' . $function[1];
		}
	}

	public static function add_command( $name, $callable, $args = [] ) {
		if ( ! defined( 'WP_CLI' ) ) {
			return false;
		}

		$valid = false;
		if ( is_callable( $callable ) ) {
			$valid = true;
		} elseif ( is_string( $callable ) && class_exists( (string) $callable ) ) {
			$valid = true;
		} elseif ( is_object( $callable ) ) {
			$valid = true;
		} elseif ( Utils\is_valid_class_and_method_pair( $callable ) ) {
			$valid = true;
		}
		if ( ! $valid ) {
			if ( is_array( $callable ) ) {
				$callable[0] = is_object( $callable[0] ) ? get_class( $callable[0] ) : $callable[0];
				$callable    = [ $callable[0], $callable[1] ];
			}
			self::error( sprintf( 'Callable %s does not exist, and cannot be registered as `wp %s`.', json_encode( $callable ), $name ) );
		}

		$addition = new CommandAddition();
		self::do_hook( "before_add_command:{$name}", $addition );

		if ( $addition->was_aborted() ) {
			self::warning( "Aborting the addition of the command '{$name}' with reason: {$addition->get_reason()}." );
			return false;
		}

		foreach ( [ 'before_invoke', 'after_invoke' ] as $when ) {
			if ( isset( $args[ $when ] ) ) {
				self::add_hook( "{$when}:{$name}", $args[ $when ] );
			}
		}

		$path = preg_split( '/\s+/', $name );

		$leaf_name = array_pop( $path );

		$command = self::get_root_command();

		while ( ! empty( $path ) ) {
			$subcommand_name = $path[0];
			$parent          = implode( ' ', $path );
			$subcommand      = $command->find_subcommand( $path );

			if ( ! $subcommand ) {
				if ( isset( $args['is_deferred'] ) && $args['is_deferred'] ) {
					$subcommand = new CompositeCommand(
						$command,
						$subcommand_name,
						new DocParser( '' )
					);

					self::debug(
						"Adding empty container for deferred command: {$name}",
						'commands'
					);

					$command->add_subcommand( $subcommand_name, $subcommand );
				} else {
					self::debug( "Deferring command: {$name}", 'commands' );

					self::defer_command_addition(
						$name,
						$parent,
						$callable,
						$args
					);

					return false;
				}
			}

			$command = $subcommand;
		}

		$leaf_command = CommandFactory::create( $leaf_name, $callable, $command );

		if ( $leaf_command instanceof CommandNamespace
			&& array_key_exists( $leaf_name, $command->get_subcommands() ) ) {
			return false;
		}

		$subcommand_name  = (array) $leaf_name;
		$existing_command = $command->find_subcommand( $subcommand_name );
		if ( $existing_command instanceof CompositeCommand && $existing_command->can_have_subcommands() ) {
			if ( $leaf_command instanceof CommandNamespace || ! $leaf_command->can_have_subcommands() ) {
				$command_to_keep = $existing_command;
			} else {
				$command_to_keep = $leaf_command;
			}

			self::merge_sub_commands( $command_to_keep, $existing_command, $leaf_command );
		}


		if ( ! $command->can_have_subcommands() ) {
			throw new Exception(
				sprintf(
					"'%s' can't have subcommands.",
					implode( ' ', Dispatcher\get_path( $command ) )
				)
			);
		}


		if ( isset( $args['shortdesc'] ) ) {
			$leaf_command->set_shortdesc( $args['shortdesc'] );
		}

		if ( isset( $args['longdesc'] ) ) {
			$leaf_command->set_longdesc( $args['longdesc'] );
		}

		if ( isset( $args['synopsis'] ) ) {
			if ( is_string( $args['synopsis'] ) ) {
				$leaf_command->set_synopsis( $args['synopsis'] );
			} elseif ( is_array( $args['synopsis'] ) ) {
				$synopsis = SynopsisParser::render( $args['synopsis'] );
				$leaf_command->set_synopsis( $synopsis );
				$long_desc = '';
				$bits      = explode( ' ', $synopsis );
				foreach ( $args['synopsis'] as $key => $arg ) {
					$long_desc .= $bits[ $key ] . "\n";
					if ( ! empty( $arg['description'] ) ) {
						$long_desc .= ': ' . $arg['description'] . "\n";
					}
					$yamlify = [];
					foreach ( [ 'default', 'options' ] as $key ) {
						if ( isset( $arg[ $key ] ) ) {
							$yamlify[ $key ] = $arg[ $key ];
						}
					}
					if ( ! empty( $yamlify ) ) {
						$long_desc .= Spyc::YAMLDump( $yamlify );
						$long_desc .= '---' . "\n";
					}
					$long_desc .= "\n";
				}
				if ( ! empty( $long_desc ) ) {
					$long_desc = rtrim( $long_desc, "\r\n" );
					$long_desc = '## OPTIONS' . "\n\n" . $long_desc;
					if ( ! empty( $args['longdesc'] ) ) {
						$long_desc .= "\n\n" . ltrim( $args['longdesc'], "\r\n" );
					}
					$leaf_command->set_longdesc( $long_desc );
				}
			}
		}

		if ( isset( $args['when'] ) ) {
			self::get_runner()->register_early_invoke( $args['when'], $leaf_command );
		}

		if ( ! empty( $parent ) ) {
			$sub_command = trim( str_replace( $parent, '', $name ) );
			self::debug( "Adding command: {$sub_command} in {$parent} Namespace", 'commands' );
		} else {
			self::debug( "Adding command: {$name}", 'commands' );
		}

		$command->add_subcommand( $leaf_name, $leaf_command );

		self::do_hook( "after_add_command:{$name}" );
		return true;
	}

	private static function merge_sub_commands(
		CompositeCommand $command_to_keep,
		CompositeCommand $old_command,
		CompositeCommand $new_command
	) {
		foreach ( $old_command->get_subcommands() as $subname => $subcommand ) {
			$command_to_keep->add_subcommand( $subname, $subcommand, false );
		}

		foreach ( $new_command->get_subcommands() as $subname => $subcommand ) {
			$command_to_keep->add_subcommand( $subname, $subcommand, true );
		}
	}

	private static function defer_command_addition( $name, $parent, $callable, $args = [] ) {
		$args['is_deferred']               = true;
		self::$deferred_additions[ $name ] = [
			'parent'   => $parent,
			'callable' => $callable,
			'args'     => $args,
		];
		self::add_hook(
			"after_add_command:$parent",
			function () use ( $name ) {
				$deferred_additions = WP_CLI::get_deferred_additions();

				if ( ! array_key_exists( $name, $deferred_additions ) ) {
					return;
				}

				$callable = $deferred_additions[ $name ]['callable'];
				$args     = $deferred_additions[ $name ]['args'];
				WP_CLI::remove_deferred_addition( $name );

				WP_CLI::add_command( $name, $callable, $args );
			}
		);
	}

	public static function get_deferred_additions() {
		return self::$deferred_additions;
	}

	public static function remove_deferred_addition( $name ) {
		if ( ! array_key_exists( $name, self::$deferred_additions ) ) {
			self::warning( "Trying to remove a non-existent command addition '{$name}'." );
		}

		unset( self::$deferred_additions[ $name ] );
	}

	public static function line( $message = '' ) {
		echo $message . "\n";
	}

	public static function log( $message ) {
		if ( null === self::$logger ) {
			return;
		}

		self::$logger->info( $message );
	}

	public static function success( $message ) {
		if ( null === self::$logger ) {
			return;
		}

		self::$logger->success( $message );
	}

	public static function debug( $message, $group = false ) {
		static $storage = [];

		if ( ! self::$logger ) {
			$storage[] = [ $message, $group ];
			return;
		}

		if ( ! empty( $storage ) ) {
			foreach ( $storage as $entry ) {
				list( $stored_message, $stored_group ) = $entry;
				self::$logger->debug( self::error_to_string( $stored_message ), $stored_group );
			}
			$storage = [];
		}

		self::$logger->debug( self::error_to_string( $message ), $group );
	}

	public static function warning( $message ) {
		if ( null === self::$logger ) {
			return;
		}

		self::$logger->warning( self::error_to_string( $message ) );
	}

	public static function error( $message, $exit = true ) {
		if ( null !== self::$logger && ! isset( self::get_runner()->assoc_args['completions'] ) ) {
			self::$logger->error( self::error_to_string( $message ) );
		}

		$return_code = false;
		if ( true === $exit ) {
			$return_code = 1;
		} elseif ( is_int( $exit ) && $exit >= 1 ) {
			$return_code = $exit;
		}

		if ( $return_code ) {
			if ( self::$capture_exit ) {
				throw new ExitException( '', $return_code );
			}
			exit( $return_code );
		}
	}

	public static function halt( $return_code ) {
		if ( self::$capture_exit ) {
			throw new ExitException( '', $return_code );
		}
		exit( $return_code );
	}

	public static function error_multi_line( $message_lines ) {
		if ( null === self::$logger ) {
			return;
		}

		if ( ! isset( self::get_runner()->assoc_args['completions'] ) && is_array( $message_lines ) ) {
			self::$logger->error_multi_line( array_map( [ __CLASS__, 'error_to_string' ], $message_lines ) );
		}
	}

	public static function confirm( $question, $assoc_args = [] ) {
		if ( ! Utils\get_flag_value( $assoc_args, 'yes' ) ) {
			fwrite( STDOUT, $question . ' [y/n] ' );

			$answer = strtolower( trim( fgets( STDIN ) ) );

			if ( 'y' !== $answer ) {
				exit;
			}
		}
	}

	public static function get_value_from_arg_or_stdin( $args, $index ) {
		if ( isset( $args[ $index ] ) ) {
			$raw_value = $args[ $index ];
		} else {
			$raw_value = '';
			$line      = fgets( STDIN );

			while ( false !== $line ) {
				$raw_value .= $line;
				$line       = fgets( STDIN );
			}
		}

		return $raw_value;
	}

	public static function read_value( $raw_value, $assoc_args = [] ) {
		if ( Utils\get_flag_value( $assoc_args, 'format' ) === 'json' ) {
			$value = json_decode( $raw_value, true );
			if ( null === $value ) {
				self::error( sprintf( 'Invalid JSON: %s', $raw_value ) );
			}
		} else {
			$value = $raw_value;
		}

		return $value;
	}

	public static function print_value( $value, $assoc_args = [] ) {
		if ( Utils\get_flag_value( $assoc_args, 'format' ) === 'json' ) {
			$value = json_encode( $value );
		} elseif ( Utils\get_flag_value( $assoc_args, 'format' ) === 'yaml' ) {
			$value = Spyc::YAMLDump( $value, 2, 0 );
		} elseif ( is_array( $value ) || is_object( $value ) ) {
			$value = var_export( $value, true );
		}

		echo $value . "\n";
	}

	public static function error_to_string( $errors ) {
		if ( is_string( $errors ) ) {
			return $errors;
		}

		$render_data = function ( $data ) {
			if ( is_array( $data ) || is_object( $data ) ) {
				return json_encode( $data );
			}

			return '"' . $data . '"';
		};

		if ( $errors instanceof WP_Error ) {
			foreach ( $errors->get_error_messages() as $message ) {
				if ( $errors->get_error_data() ) {
					return $message . ' ' . $render_data( $errors->get_error_data() );
				}

				return $message;
			}
		}

		if ( ( interface_exists( 'Throwable' ) && ( $errors instanceof Throwable ) ) || ( $errors instanceof Exception ) ) {
			return get_class( $errors ) . ': ' . $errors->getMessage();
		}

		throw new InvalidArgumentException(
			sprintf(
				"Unsupported argument type passed to WP_CLI::error_to_string(): '%s'",
				gettype( $errors )
			)
		);
	}

	public static function launch( $command, $exit_on_error = true, $return_detailed = false ) {
		Utils\check_proc_available( 'launch' );

		$proc    = Process::create( $command );
		$results = $proc->run();

		if ( -1 === $results->return_code ) {
			self::warning( "Spawned process returned exit code {$results->return_code}, which could be caused by a custom compiled version of PHP that uses the --enable-sigchild option." );
		}

		if ( $results->return_code && $exit_on_error ) {
			exit( $results->return_code );
		}

		if ( $return_detailed ) {
			return $results;
		}

		return $results->return_code;
	}

	public static function launch_self( $command, $args = [], $assoc_args = [], $exit_on_error = true, $return_detailed = false, $runtime_args = [] ) {
		$reused_runtime_args = [
			'path',
			'url',
			'user',
			'allow-root',
		];

		foreach ( $reused_runtime_args as $key ) {
			if ( isset( $runtime_args[ $key ] ) ) {
				$assoc_args[ $key ] = $runtime_args[ $key ];
				continue;
			}

			$value = self::get_runner()->config[ $key ];
			if ( $value ) {
				$assoc_args[ $key ] = $value;
			}
		}

		$php_bin = escapeshellarg( Utils\get_php_binary() );

		$script_path = $GLOBALS['argv'][0];

		if ( getenv( 'WP_CLI_CONFIG_PATH' ) ) {
			$config_path = getenv( 'WP_CLI_CONFIG_PATH' );
		} else {
			$config_path = Utils\get_home_dir() . '/.wp-cli/config.yml';
		}
		$config_path = escapeshellarg( $config_path );

		$args       = implode( ' ', array_map( 'escapeshellarg', $args ) );
		$assoc_args = Utils\assoc_args_to_str( $assoc_args );

		$full_command = "WP_CLI_CONFIG_PATH={$config_path} {$php_bin} {$script_path} {$command} {$args} {$assoc_args}";

		return self::launch( $full_command, $exit_on_error, $return_detailed );
	}

	public static function get_php_binary() {
		return Utils\get_php_binary();
	}

	public static function has_config( $key ) {
		return array_key_exists( $key, self::get_runner()->config );
	}

	public static function get_config( $key = null ) {
		if ( null === $key ) {
			return self::get_runner()->config;
		}

		if ( ! self::has_config( $key ) ) {
			self::warning( "Unknown config option '$key'." );
			return null;
		}

		return self::get_runner()->config[ $key ];
	}

	public static function runcommand( $command, $options = [] ) {
		$defaults     = [
			'launch'       => true,
			'exit_error'   => true,
			'return'       => false,
			'parse'        => false,
			'command_args' => [],
		];
		$options      = array_merge( $defaults, $options );
		$launch       = $options['launch'];
		$exit_error   = $options['exit_error'];
		$return       = $options['return'];
		$parse        = $options['parse'];
		$command_args = $options['command_args'];

		if ( ! empty( $command_args ) ) {
			$command .= ' ' . implode( ' ', $command_args );
		}

		$retval = null;
		if ( $launch ) {
			Utils\check_proc_available( 'launch option' );

			$descriptors = [
				0 => STDIN,
				1 => STDOUT,
				2 => STDERR,
			];

			if ( $return ) {
				$descriptors = [
					0 => STDIN,
					1 => [ 'pipe', 'w' ],
					2 => [ 'pipe', 'w' ],
				];
			}

			$php_bin     = escapeshellarg( Utils\get_php_binary() );
			$script_path = $GLOBALS['argv'][0];

			$configurator = self::get_configurator();
			$argv         = array_slice( $GLOBALS['argv'], 1 );

			list( $ignore1, $ignore2, $runtime_config ) = $configurator->parse_args( $argv );
			foreach ( $runtime_config as $k => $v ) {
				if ( preg_match( "|^--{$k}=?$|", $command ) ) {
					unset( $runtime_config[ $k ] );
				}
			}
			$runtime_config = Utils\assoc_args_to_str( $runtime_config );

			$runcommand = "{$php_bin} {$script_path} {$runtime_config} {$command}";

			$pipes = [];
			$proc  = Utils\proc_open_compat( $runcommand, $descriptors, $pipes, getcwd() );

			$stdout = '';
			$stderr = '';

			if ( $return ) {
				$stdout = stream_get_contents( $pipes[1] );
				fclose( $pipes[1] );
				$stderr = stream_get_contents( $pipes[2] );
				fclose( $pipes[2] );
			}
			$return_code = proc_close( $proc );
			if ( -1 === $return_code ) {
				self::warning( 'Spawned process returned exit code -1, which could be caused by a custom compiled version of PHP that uses the --enable-sigchild option.' );
			} elseif ( $return_code && $exit_error ) {
				exit( $return_code );
			}
			if ( true === $return || 'stdout' === $return ) {
				$retval = trim( $stdout );
			} elseif ( 'stderr' === $return ) {
				$retval = trim( $stderr );
			} elseif ( 'return_code' === $return ) {
				$retval = $return_code;
			} elseif ( 'all' === $return ) {
				$retval = (object) [
					'stdout'      => trim( $stdout ),
					'stderr'      => trim( $stderr ),
					'return_code' => $return_code,
				];
			}
		} else {
			$configurator                               = self::get_configurator();
			$argv                                       = Utils\parse_str_to_argv( $command );
			list( $args, $assoc_args, $runtime_config ) = $configurator->parse_args( $argv );
			if ( $return ) {
				$existing_logger = self::$logger;
				self::$logger    = new Execution();
				self::$logger->ob_start();
			}
			if ( ! $exit_error ) {
				self::$capture_exit = true;
			}
			try {
				self::get_runner()->run_command(
					$args,
					$assoc_args,
					[
						'back_compat_conversions' => true,
					]
				);
				$return_code = 0;
			} catch ( ExitException $e ) {
				$return_code = $e->getCode();
			}
			if ( $return ) {
				$execution_logger = self::$logger;
				$execution_logger->ob_end();
				self::$logger = $existing_logger;
				$stdout       = $execution_logger->stdout;
				$stderr       = $execution_logger->stderr;
				if ( true === $return || 'stdout' === $return ) {
					$retval = trim( $stdout );
				} elseif ( 'stderr' === $return ) {
					$retval = trim( $stderr );
				} elseif ( 'return_code' === $return ) {
					$retval = $return_code;
				} elseif ( 'all' === $return ) {
					$retval = (object) [
						'stdout'      => trim( $stdout ),
						'stderr'      => trim( $stderr ),
						'return_code' => $return_code,
					];
				}
			}
			if ( ! $exit_error ) {
				self::$capture_exit = false;
			}
		}
		if ( ( true === $return || 'stdout' === $return )
			&& 'json' === $parse ) {
			$retval = json_decode( $retval, true );
		}
		return $retval;
	}

	public static function run_command( $args, $assoc_args = [] ) {
		self::get_runner()->run_command( $args, $assoc_args );
	}




	public static function add_man_dir() {
		trigger_error( 'WP_CLI::add_man_dir() is deprecated. Add docs inline.', E_USER_WARNING );
	}

	public static function out( $str ) {
		fwrite( STDOUT, $str );
	}

	public static function addCommand( $name, $class ) {
		trigger_error(
			sprintf(
				'wp %s: %s is deprecated. use WP_CLI::add_command() instead.',
				$name,
				__FUNCTION__
			),
			E_USER_WARNING
		);
		self::add_command( $name, $class );
	}
}
