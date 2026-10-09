<?php


namespace WP_CLI\Utils;

use ArrayIterator;
use cli;
use cli\progress\Bar;
use cli\Shell;
use Closure;
use Composer\Semver\Comparator;
use Composer\Semver\Semver;
use Exception;
use Mustache_Engine;
use ReflectionFunction;
use RuntimeException;
use WP_CLI;
use WP_CLI\ExitException;
use WP_CLI\Formatter;
use WP_CLI\Inflector;
use WP_CLI\Iterators\Transform;
use WP_CLI\NoOp;
use WP_CLI\Process;
use WP_CLI\RequestsLibrary;

const PHAR_STREAM_PREFIX = 'phar://';

const FILE_DIR_PATTERN = '%(?>#.*?$)|(?>//.*?$)|(?>/\*.*?\*/)|(?>\'(?:(?=(\\\\?))\1.)*?\')|(?>"(?:(?=(\\\\?))\2.)*?")|(?<file>\b__FILE__\b)|(?<dir>\b__DIR__\b)%ms';

function inside_phar( $path = null ) {
	if ( null === $path ) {
		if ( ! defined( 'WP_CLI_ROOT' ) ) {
			return false;
		}

		$path = WP_CLI_ROOT;
	}

	return 0 === strpos( $path, PHAR_STREAM_PREFIX );
}

function extract_from_phar( $path ) {
	if ( ! inside_phar( $path ) ) {
		return $path;
	}

	$fname = basename( $path );

	$tmp_path = get_temp_dir() . uniqid( 'wp-cli-extract-from-phar-', true ) . "-$fname";

	copy( $path, $tmp_path );

	register_shutdown_function(
		function () use ( $tmp_path ) {
			if ( file_exists( $tmp_path ) ) {
				unlink( $tmp_path );
			}
		}
	);

	return $tmp_path;
}

function load_dependencies() {
	if ( inside_phar() ) {
		if ( file_exists( WP_CLI_ROOT . '/vendor/autoload.php' ) ) {
			require WP_CLI_ROOT . '/vendor/autoload.php';
		} elseif ( file_exists( dirname( dirname( WP_CLI_ROOT ) ) . '/autoload.php' ) ) {
			require dirname( dirname( WP_CLI_ROOT ) ) . '/autoload.php';
		}
		return;
	}

	$has_autoload = false;

	foreach ( get_vendor_paths() as $vendor_path ) {
		if ( file_exists( $vendor_path . '/autoload.php' ) ) {
			require $vendor_path . '/autoload.php';
			$has_autoload = true;
			break;
		}
	}

	if ( ! $has_autoload ) {
		fwrite( STDERR, "Internal error: Can't find Composer autoloader.\nTry running: composer install\n" );
		exit( 3 );
	}
}

function get_vendor_paths() {
	$vendor_paths        = [
		WP_CLI_ROOT . '/../../../vendor',
		WP_CLI_ROOT . '/vendor',
	];
	$maybe_composer_json = WP_CLI_ROOT . '/../../../composer.json';
	if ( file_exists( $maybe_composer_json ) && is_readable( $maybe_composer_json ) ) {
		$composer = json_decode( file_get_contents( $maybe_composer_json ) );
		if ( ! empty( $composer->config ) && ! empty( $composer->config->{'vendor-dir'} ) ) {
			array_unshift( $vendor_paths, WP_CLI_ROOT . '/../../../' . $composer->config->{'vendor-dir'} );
		}
	}
	return $vendor_paths;
}

function load_file( $path ) {
	require_once $path;
}

function load_command( $name ) {
	$path = WP_CLI_ROOT . "/php/commands/$name.php";

	if ( is_readable( $path ) ) {
		include_once $path;
	}
}

function iterator_map( $it, $fn ) {
	if ( is_array( $it ) ) {
		$it = new ArrayIterator( $it );
	}

	if ( ! method_exists( $it, 'add_transform' ) ) {
		$it = new Transform( $it );
	}

	foreach ( array_slice( func_get_args(), 1 ) as $fn ) {
		$it->add_transform( $fn );
	}

	return $it;
}

function find_file_upward( $files, $dir = null, $stop_check = null ) {
	$files = (array) $files;
	if ( is_null( $dir ) ) {
		$dir = getcwd();
	}
	while ( is_readable( $dir ) ) {
		if ( is_callable( $stop_check ) && call_user_func( $stop_check, $dir ) ) {
			return null;
		}

		foreach ( $files as $file ) {
			$path = $dir . DIRECTORY_SEPARATOR . $file;
			if ( file_exists( $path ) ) {
				return $path;
			}
		}

		$parent_dir = dirname( $dir );
		if ( empty( $parent_dir ) || $parent_dir === $dir ) {
			break;
		}
		$dir = $parent_dir;
	}
	return null;
}

function is_path_absolute( $path ) {
	if ( isset( $path[1] ) && ':' === $path[1] ) {
		return true;
	}

	return isset( $path[0] ) && '/' === $path[0];
}

function args_to_str( $args ) {
	return ' ' . implode( ' ', array_map( 'escapeshellarg', $args ) );
}

function assoc_args_to_str( $assoc_args ) {
	$str = '';

	foreach ( $assoc_args as $key => $value ) {
		if ( true === $value ) {
			$str .= " --$key";
		} elseif ( is_array( $value ) ) {
			foreach ( $value as $v ) {
				$str .= assoc_args_to_str(
					[
						$key => $v,
					]
				);
			}
		} else {
			$str .= " --$key=" . escapeshellarg( $value );
		}
	}

	return $str;
}

function esc_cmd( $cmd ) {
	if ( func_num_args() < 2 ) {
		trigger_error( 'esc_cmd() requires at least two arguments.', E_USER_WARNING );
	}

	$args = func_get_args();

	$cmd = array_shift( $args );

	return vsprintf( $cmd, array_map( 'escapeshellarg', $args ) );
}

function locate_wp_config() {
	static $path;

	if ( null === $path ) {
		$path = false;

		if ( getenv( 'WP_CONFIG_PATH' ) && file_exists( getenv( 'WP_CONFIG_PATH' ) ) ) {
			$path = getenv( 'WP_CONFIG_PATH' );
		} elseif ( file_exists( ABSPATH . 'wp-config.php' ) ) {
			$path = ABSPATH . 'wp-config.php';
		} elseif ( file_exists( dirname( ABSPATH ) . '/wp-config.php' ) && ! file_exists( dirname( ABSPATH ) . '/wp-settings.php' ) ) {
			$path = dirname( ABSPATH ) . '/wp-config.php';
		}

		if ( $path ) {
			$path = realpath( $path );
		}
	}

	return $path;
}

function wp_version_compare( $since, $operator ) {
	$wp_version = str_replace( '-src', '', $GLOBALS['wp_version'] );
	$since      = str_replace( '-src', '', $since );
	return version_compare( $wp_version, $since, $operator );
}

function format_items( $format, $items, $fields ) {
	$assoc_args = [
		'format' => $format,
		'fields' => $fields,
	];
	$formatter  = new Formatter( $assoc_args );
	$formatter->display_items( $items );
}

function write_csv( $fd, $rows, $headers = [] ) {
	if ( ! empty( $headers ) ) {
		$headers = array_map( __NAMESPACE__ . '\escape_csv_value', $headers );
		fputcsv( $fd, $headers, ',', '"', '\\' );
	}

	foreach ( $rows as $row ) {
		if ( ! empty( $headers ) ) {
			$row = pick_fields( $row, $headers );
		}

		$row = array_map( __NAMESPACE__ . '\escape_csv_value', $row );
		fputcsv( $fd, array_values( $row ), ',', '"', '\\' );
	}
}

function pick_fields( $item, $fields ) {
	$values = [];

	if ( is_object( $item ) ) {
		foreach ( $fields as $field ) {
			$values[ $field ] = isset( $item->$field ) ? $item->$field : null;
		}
	} else {
		foreach ( $fields as $field ) {
			$values[ $field ] = isset( $item[ $field ] ) ? $item[ $field ] : null;
		}
	}

	return $values;
}

function launch_editor_for_input( $input, $title = 'WP-CLI', $ext = 'tmp' ) {

	check_proc_available( 'launch_editor_for_input' );

	$tmpdir = get_temp_dir();

	do {
		$tmpfile  = basename( $title );
		$tmpfile  = preg_replace( '|\.[^.]*$|', '', $tmpfile );
		$tmpfile .= '-' . substr( md5( (string) mt_rand() ), 0, 6 );
		$tmpfile  = $tmpdir . $tmpfile . '.' . $ext;
		$fp       = fopen( $tmpfile, 'xb' );
		if ( ! $fp && is_writable( $tmpdir ) && file_exists( $tmpfile ) ) {
			$tmpfile = '';
			continue;
		}
		if ( $fp ) {
			fclose( $fp );
		}
	} while ( ! $tmpfile );

	if ( ! $tmpfile ) {
		WP_CLI::error( 'Error creating temporary file.' );
	}

	file_put_contents( $tmpfile, $input );

	$editor = getenv( 'EDITOR' );
	if ( ! $editor ) {
		$editor = is_windows() ? 'notepad' : 'vi';
	}

	$descriptorspec = [ STDIN, STDOUT, STDERR ];
	$process        = proc_open_compat( "$editor " . escapeshellarg( $tmpfile ), $descriptorspec, $pipes );
	$r              = proc_close( $process );
	if ( $r ) {
		exit( $r );
	}

	$output = file_get_contents( $tmpfile );

	unlink( $tmpfile );

	if ( $output === $input ) {
		return false;
	}

	return $output;
}

function mysql_host_to_cli_args( $raw_host ) {
	$assoc_args = [];

	if ( substr( $raw_host, 0, 2 ) === 'p:' ) {
		$raw_host = substr_replace( $raw_host, '', 0, 2 );
	}

	$host_parts = explode( ':', $raw_host );
	if ( count( $host_parts ) === 2 ) {
		list( $assoc_args['host'], $extra ) = $host_parts;
		$extra                              = trim( $extra );
		if ( is_numeric( $extra ) ) {
			$assoc_args['port']     = (int) $extra;
			$assoc_args['protocol'] = 'tcp';
		} elseif ( '' !== $extra ) {
			$assoc_args['socket'] = $extra;
		}
	} else {
		$assoc_args['host'] = $raw_host;
	}

	return $assoc_args;
}

function run_mysql_command( $cmd, $assoc_args, $_ = null, $send_to_shell = true, $interactive = false ) {
	check_proc_available( 'run_mysql_command' );

	$descriptors = ( $interactive || $send_to_shell ) ?
		[
			0 => STDIN,
			1 => STDOUT,
			2 => STDERR,
		] :
		[
			0 => STDIN,
			1 => [ 'pipe', 'w' ],
			2 => [ 'pipe', 'w' ],
		];

	$stdout = '';
	$stderr = '';
	$pipes  = [];

	if ( isset( $assoc_args['host'] ) ) {
		$assoc_args = array_merge( $assoc_args, mysql_host_to_cli_args( $assoc_args['host'] ) );
	}

	if ( isset( $assoc_args['pass'] ) ) {
		$old_password = getenv( 'MYSQL_PWD' );
		putenv( 'MYSQL_PWD=' . $assoc_args['pass'] );
		unset( $assoc_args['pass'] );
	}

	$final_cmd = force_env_on_nix_systems( $cmd ) . assoc_args_to_str( $assoc_args );

	WP_CLI::debug( 'Final MySQL command: ' . $final_cmd, 'db' );
	$process = proc_open_compat( $final_cmd, $descriptors, $pipes );

	if ( isset( $old_password ) ) {
		putenv( 'MYSQL_PWD=' . $old_password );
	}

	if ( ! $process ) {
		WP_CLI::debug( 'Failed to create a valid process using proc_open_compat()', 'db' );
		exit( 1 );
	}

	if ( is_resource( $process ) && ! $send_to_shell && ! $interactive ) {
		$stdout = stream_get_contents( $pipes[1] );
		$stderr = stream_get_contents( $pipes[2] );

		fclose( $pipes[1] );
		fclose( $pipes[2] );
	}

	$exit_code = proc_close( $process );

	if ( $exit_code && ( $send_to_shell || $interactive ) ) {
		exit( $exit_code );
	}

	return [
		$stdout,
		$stderr,
		$exit_code,
	];
}

function mustache_render( $template_name, $data = [] ) {
	if ( ! file_exists( $template_name ) ) {
		$template_name = WP_CLI_ROOT . "/templates/$template_name";
	}

	$template = file_get_contents( $template_name );

	$mustache = new Mustache_Engine(
		[
			'escape' => function ( $val ) {
				return $val; },
		]
	);

	return $mustache->render( $template, $data );
}

function make_progress_bar( $message, $count, $interval = 100 ) {
	if ( Shell::isPiped() ) {
		return new NoOp();
	}

	return new Bar( $message, $count, $interval );
}

function parse_url( $url, $component = - 1, $auto_add_scheme = true ) {
	if (
		function_exists( 'wp_parse_url' )
		&& (
			-1 === $component
			|| wp_version_compare( '4.7', '>=' )
		)
	) {
		$url_parts = wp_parse_url( $url, $component );
	} else {
		$url_parts = \parse_url( $url, $component );
	}

	if ( $auto_add_scheme && ! parse_url( $url, PHP_URL_SCHEME, false ) ) {
		$url_parts = parse_url( 'http://' . $url, $component, false );
	}

	return $url_parts;
}

function is_windows() {
	$test_is_windows = getenv( 'WP_CLI_TEST_IS_WINDOWS' );
	return false !== $test_is_windows ? (bool) $test_is_windows : strtoupper( substr( PHP_OS, 0, 3 ) ) === 'WIN';
}

function replace_path_consts( $source, $path ) {
	$file = addslashes( $path );

	if ( file_exists( $file ) ) {
		$file = realpath( $file );
	}

	$dir = dirname( $file );

	return preg_replace_callback(
		FILE_DIR_PATTERN,
		static function ( $matches ) use ( $file, $dir ) {
			if ( ! empty( $matches['file'] ) ) {
				return "'{$file}'";
			}

			if ( ! empty( $matches['dir'] ) ) {
				return "'{$dir}'";
			}

			return $matches[0];
		},
		$source
	);
}

function http_request( $method, $url, $data = null, $headers = [], $options = [] ) {
	$insecure      = isset( $options['insecure'] ) && (bool) $options['insecure'];
	$halt_on_error = ! isset( $options['halt_on_error'] ) || (bool) $options['halt_on_error'];
	unset( $options['halt_on_error'] );

	if ( ! isset( $options['verify'] ) ) {
		$options['verify'] = ! empty( ini_get( 'curl.cainfo' ) ) ? ini_get( 'curl.cainfo' ) : true;
	}

	$options = WP_CLI::do_hook( 'http_request_options', $options );

	RequestsLibrary::register_autoloader();

	$request_method = [ RequestsLibrary::get_class_name(), 'request' ];

	try {
		try {
			return $request_method( $url, $headers, $data, $method, $options );
		} catch ( Exception $exception ) {
			if ( RequestsLibrary::is_requests_exception( $exception ) ) {
				if (
					true !== $options['verify']
					|| 'curlerror' !== $exception->getType()
					|| curl_errno( $exception->getData() ) !== CURLE_SSL_CACERT
				) {
					throw $exception;
				}

				$options['verify'] = get_default_cacert( $halt_on_error );

				return $request_method( $url, $headers, $data, $method, $options );
			}
			throw $exception;
		}
	} catch ( Exception $exception ) {
		if ( RequestsLibrary::is_requests_exception( $exception ) ) {
			if (
				! $insecure
				||
				'curlerror' !== $exception->getType()
				||
				! in_array( curl_errno( $exception->getData() ), [ CURLE_SSL_CONNECT_ERROR, CURLE_SSL_CERTPROBLEM, 77   ], true )
			) {
				$error_msg = sprintf( "Failed to get url '%s': %s.", $url, $exception->getMessage() );
				if ( $halt_on_error ) {
					WP_CLI::error( $error_msg );
				}
				throw new RuntimeException( $error_msg, 0, $exception );
			}

			$warning = sprintf(
				"Re-trying without verify after failing to get verified url '%s' %s.",
				$url,
				$exception->getMessage()
			);
			WP_CLI::warning( $warning );

			$options['verify'] = false;

			try {
				return $request_method( $url, $headers, $data, $method, $options );
			} catch ( Exception $exception ) {
				if ( RequestsLibrary::is_requests_exception( $exception ) ) {
					$error_msg = sprintf( "Failed to get non-verified url '%s' %s.", $url, $exception->getMessage() );
					if ( $halt_on_error ) {
						WP_CLI::error( $error_msg );
					}
					throw new RuntimeException( $error_msg, 0, $exception );
				}
				throw $exception;
			}
		}
		throw $exception;
	}
}

function get_default_cacert( $halt_on_error = false ) {
	$cert_path = RequestsLibrary::get_bundled_certificate_path();
	$error_msg = 'Cannot find SSL certificate.';

	if ( inside_phar( $cert_path ) ) {
		return extract_from_phar( $cert_path );
	}

	if ( file_exists( $cert_path ) ) {
		return $cert_path;
	}

	if ( $halt_on_error ) {
		WP_CLI::error( $error_msg );
	}

	throw new RuntimeException( $error_msg );
}

function increment_version( $current_version, $new_version ) {
	$current_version    = explode( '-', $current_version, 2 );
	$current_version[0] = explode( '.', $current_version[0] );

	switch ( $new_version ) {
		case 'same':
			break;

		case 'patch':
			++$current_version[0][2];

			$current_version = [ $current_version[0] ];
			break;

		case 'minor':
			++$current_version[0][1];
			$current_version[0][2] = 0;

			$current_version = [ $current_version[0] ];
			break;

		case 'major':
			++$current_version[0][0];
			$current_version[0][1] = 0;
			$current_version[0][2] = 0;

			$current_version = [ $current_version[0] ];
			break;

		default:
			$current_version = [ [ $new_version ] ];
			break;
	}

	$current_version[0] = implode( '.', $current_version[0] );
	$current_version    = implode( '-', $current_version );

	return $current_version;
}

function get_named_sem_ver( $new_version, $original_version ) {

	if ( ! Comparator::greaterThan( $new_version, $original_version ) ) {
		return '';
	}

	$parts = explode( '-', $original_version );
	$bits  = explode( '.', $parts[0] );
	$major = $bits[0];
	if ( isset( $bits[1] ) ) {
		$minor = $bits[1];
	}
	if ( isset( $bits[2] ) ) {
		$patch = $bits[2];
	}

	try {
		if ( isset( $minor ) && Semver::satisfies( $new_version, "{$major}.{$minor}.x" ) ) {
			return 'patch';
		}

		if ( Semver::satisfies( $new_version, "{$major}.x.x" ) ) {
			return 'minor';
		}
	} catch ( \UnexpectedValueException $e ) {
		return '';
	}

	return 'major';
}

function get_flag_value( $assoc_args, $flag, $default = null ) {
	return isset( $assoc_args[ $flag ] ) ? $assoc_args[ $flag ] : $default;
}

function get_home_dir() {
	$home = getenv( 'HOME' );
	if ( ! $home ) {
		$home = getenv( 'HOMEDRIVE' ) . getenv( 'HOMEPATH' );
	}

	return rtrim( $home, '/\\' );
}

function trailingslashit( $string ) {
	if ( ! is_string( $string ) ) {
		return '/';
	}

	return rtrim( $string, '/\\' ) . '/';
}

function normalize_path( $path ) {
	$path = str_replace( '\\', '/', $path );
	$path = preg_replace( '|(?<=.)/+|', '/', $path );
	if ( ':' === substr( $path, 1, 1 ) ) {
		$path = ucfirst( $path );
	}
	return $path;
}


function normalize_eols( $str ) {
	return str_replace( "\r\n", "\n", $str );
}

function get_temp_dir() {
	static $temp = '';

	if ( $temp ) {
		return $temp;
	}

	$temp = trailingslashit( sys_get_temp_dir() );

	if ( ! is_writable( $temp ) ) {
		WP_CLI::warning( "Temp directory isn't writable: {$temp}" );
	}

	return $temp;
}

function parse_ssh_url( $url, $component = -1 ) {
	preg_match( '#^((docker|docker\-compose|docker\-compose\-run|ssh|vagrant):)?(([^@:]+)@)?([^:/~]+)(:([\d]*))?((/|~)(.+))?$#', $url, $matches );
	$bits = [];
	foreach ( [
		2 => 'scheme',
		4 => 'user',
		5 => 'host',
		7 => 'port',
		8 => 'path',
	] as $i => $key ) {
		if ( ! empty( $matches[ $i ] ) ) {
			$bits[ $key ] = $matches[ $i ];
		}
	}

	if ( preg_match( '/^vagrant:?/', $url ) ) {
		if ( 'vagrant' === $bits['host'] && empty( $bits['scheme'] ) ) {
			$bits['scheme'] = 'vagrant';
			$bits['host']   = '';
		}
	}

	switch ( $component ) {
		case PHP_URL_SCHEME:
			return isset( $bits['scheme'] ) ? $bits['scheme'] : null;
		case PHP_URL_USER:
			return isset( $bits['user'] ) ? $bits['user'] : null;
		case PHP_URL_HOST:
			return isset( $bits['host'] ) ? $bits['host'] : null;
		case PHP_URL_PATH:
			return isset( $bits['path'] ) ? $bits['path'] : null;
		case PHP_URL_PORT:
			return isset( $bits['port'] ) ? $bits['port'] : null;
		default:
			return $bits;
	}
}

function report_batch_operation_results( $noun, $verb, $total, $successes, $failures, $skips = null ) {
	$plural_noun           = $noun . 's';
	$past_tense_verb       = past_tense_verb( $verb );
	$past_tense_verb_upper = ucfirst( $past_tense_verb );
	if ( $failures ) {
		$failed_skipped_message = null === $skips ? '' : " ({$failures} failed" . ( $skips ? ", {$skips} skipped" : '' ) . ')';
		if ( $successes ) {
			WP_CLI::error( "Only {$past_tense_verb} {$successes} of {$total} {$plural_noun}{$failed_skipped_message}." );
		} else {
			WP_CLI::error( "No {$plural_noun} {$past_tense_verb}{$failed_skipped_message}." );
		}
	} else {
		$skipped_message = $skips ? " ({$skips} skipped)" : '';
		if ( $successes || $skips ) {
			WP_CLI::success( "{$past_tense_verb_upper} {$successes} of {$total} {$plural_noun}{$skipped_message}." );
		} else {
			$message = $total > 1 ? ucfirst( $plural_noun ) : ucfirst( $noun );
			WP_CLI::success( "{$message} already {$past_tense_verb}." );
		}
	}
}

function parse_str_to_argv( $arguments ) {
	preg_match_all( '/(?:--[^\s=]+=(["\'])((\\{2})*|(?:[^\1]+?[^\\\\](\\{2})*))\1|--[^\s=]+=[^\s]+|--[^\s=]+|(["\'])((\\{2})*|(?:[^\5]+?[^\\\\](\\{2})*))\5|[^\s]+)/', $arguments, $matches );
	$argv = $matches[0];
	return array_map(
		static function ( $arg ) {
			foreach ( [ '"', "'" ] as $char ) {
				if ( substr( $arg, 0, 1 ) === $char && substr( $arg, -1 ) === $char ) {
					$arg = substr( $arg, 1, -1 );
					break;
				}
			}
			return $arg;
		},
		$argv
	);
}

function basename( $path, $suffix = '' ) {
	return urldecode( \basename( str_replace( [ '%2F', '%5C' ], '/', urlencode( $path ) ), $suffix ) );
}

function isPiped() {
	$shell_pipe = getenv( 'SHELL_PIPE' );

	if ( false !== $shell_pipe ) {
		return filter_var( $shell_pipe, FILTER_VALIDATE_BOOLEAN );
	}

	return function_exists( 'posix_isatty' ) && ! posix_isatty( STDOUT );
}

function expand_globs( $paths, $flags = 'default' ) {
	$glob_func = 'glob';
	if ( 'default' === $flags ) {
		if ( ! defined( 'GLOB_BRACE' ) || getenv( 'WP_CLI_TEST_EXPAND_GLOBS_NO_GLOB_BRACE' ) ) {
			$glob_func = 'WP_CLI\Utils\glob_brace';
		} else {
			$flags = GLOB_BRACE;
		}
	}

	$expanded = [];

	foreach ( (array) $paths as $path ) {
		$matching = [ $path ];

		if ( preg_match( '/[' . preg_quote( '*?[]{}!', '/' ) . ']/', $path ) ) {
			$matching = $glob_func( $path, $flags ) ?: [];
		}
		$expanded = array_merge( $expanded, $matching );
	}

	return array_values( array_unique( $expanded ) );
}

/**
 * Simulate a `glob()` with the `GLOB_BRACE` flag set. For systems (eg Alpine Linux) built against a libc library (eg https://www.musl-libc.org/) that lacks it.
 * Copied and adapted from Zend Framework's `Glob::fallbackGlob()` and Glob::nextBraceSub()`.
 *
 * Zend Framework (https://framework.zend.com/)
 *
 * @link      https://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2015 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://framework.zend.com/license/new-bsd New BSD License
 *
 * @param string $pattern     Filename pattern.
 * @param void   $dummy_flags Not used.
 * @return array<string> Array of paths.
 */
function glob_brace( $pattern, $dummy_flags = null ) {

	static $next_brace_sub;
	if ( ! $next_brace_sub ) {
		$next_brace_sub = function ( $pattern, $current ) {
			$length = strlen( $pattern );
			$depth  = 0;

			while ( $current < $length ) {
				if ( '\\' === $pattern[ $current ] ) {
					if ( ++$current === $length ) {
						break;
					}
					++$current;
				} else {
					if ( ( '}' === $pattern[ $current ] && 0 === $depth-- ) || ( ',' === $pattern[ $current ] && 0 === $depth ) ) {
						break;
					}

					if ( '{' === $pattern[ $current++ ] ) {
						++$depth;
					}
				}
			}

			return $current < $length ? $current : null;
		};
	}

	$length = strlen( $pattern );

	for ( $begin = 0; $begin < $length; $begin++ ) {
		if ( '\\' === $pattern[ $begin ] ) {
			++$begin;
		} elseif ( '{' === $pattern[ $begin ] ) {
			break;
		}
	}

	$next = $next_brace_sub( $pattern, $begin + 1 );
	if ( null === $next ) {
		return glob( $pattern );
	}

	$rest = $next;

	while ( '}' !== $pattern[ $rest ] ) {
		$rest = $next_brace_sub( $pattern, $rest + 1 );
		if ( null === $rest ) {
			return glob( $pattern );
		}
	}

	$paths = [];
	$p     = $begin + 1;

	do {
		$subpattern = substr( $pattern, 0, $begin )
			. substr( $pattern, $p, $next - $p )
			. substr( $pattern, $rest + 1 );

		$result = glob_brace( $subpattern );
		if ( ! empty( $result ) ) {
			$paths = array_merge( $paths, $result );
		}

		if ( '}' === $pattern[ $next ] ) {
			break;
		}

		$p    = $next + 1;
		$next = $next_brace_sub( $pattern, $p );
	} while ( null !== $next );

	return array_values( array_unique( $paths ) );
}

function get_suggestion( $target, array $options, $threshold = 2 ) {

	$suggestion_map = [
		'add'        => 'create',
		'check'      => 'check-update',
		'capability' => 'cap',
		'clear'      => 'flush',
		'decrement'  => 'decr',
		'del'        => 'delete',
		'directory'  => 'dir',
		'exec'       => 'eval',
		'exec-file'  => 'eval-file',
		'increment'  => 'incr',
		'language'   => 'locale',
		'lang'       => 'locale',
		'new'        => 'create',
		'number'     => 'count',
		'remove'     => 'delete',
		'regen'      => 'regenerate',
		'rep'        => 'replace',
		'repl'       => 'replace',
		'trash'      => 'delete',
		'v'          => 'version',
	];

	if ( array_key_exists( $target, $suggestion_map ) && in_array( $suggestion_map[ $target ], $options, true ) ) {
		return $suggestion_map[ $target ];
	}

	if ( empty( $options ) ) {
		return '';
	}
	foreach ( $options as $option ) {
		$distance               = levenshtein( $option, $target );
		$levenshtein[ $option ] = $distance;
	}

	asort( $levenshtein );

	reset( $levenshtein );
	$suggestion = key( $levenshtein );

	return $levenshtein[ $suggestion ] <= $threshold && $suggestion !== $target
		? (string) $suggestion
		: '';
}

function phar_safe_path( $path ) {

	if ( ! inside_phar() ) {
		return $path;
	}

	return str_replace(
		PHAR_STREAM_PREFIX . rtrim( WP_CLI_PHAR_PATH, '/' ) . '/',
		PHAR_STREAM_PREFIX,
		$path
	);
}

function force_env_on_nix_systems( $command ) {
	$env_prefix     = '/usr/bin/env ';
	$env_prefix_len = strlen( $env_prefix );
	if ( is_windows() ) {
		if ( 0 === strncmp( $command, $env_prefix, $env_prefix_len ) ) {
			$command = substr( $command, $env_prefix_len );
		}
	} elseif ( 0 !== strncmp( $command, $env_prefix, $env_prefix_len ) ) {
		$command = $env_prefix . $command;
	}
	return $command;
}

function check_proc_available( $context = null, $return = false ) {
	if ( ! function_exists( 'proc_open' ) || ! function_exists( 'proc_close' ) ) {
		if ( $return ) {
			return false;
		}
		$msg = 'The PHP functions `proc_open()` and/or `proc_close()` are disabled. Please check your PHP ini directive `disable_functions` or suhosin settings.';
		if ( $context ) {
			WP_CLI::error( sprintf( "Cannot do '%s': %s", $context, $msg ) );
		} else {
			WP_CLI::error( $msg );
		}
	}
	return true;
}

function past_tense_verb( $verb ) {
	static $irregular = [
		'reset' => 'reset',
	];
	if ( isset( $irregular[ $verb ] ) ) {
		return $irregular[ $verb ];
	}
	$last = substr( $verb, -1 );
	if ( 'e' === $last ) {
		$verb = substr( $verb, 0, -1 );
	} elseif ( 'y' === $last && ! preg_match( '/[aeiou]y$/', $verb ) ) {
		$verb = substr( $verb, 0, -1 ) . 'i';
	} elseif ( preg_match( '/^[^aeiou]*[aeiou][^aeiouhwxy]$/', $verb ) ) {
		$verb .= $last;
	}
	return $verb . 'ed';
}

function get_php_binary() {
	if ( inside_phar() ) {
		return PHP_BINARY;
	}

	$wp_cli_php_used = getenv( 'WP_CLI_PHP_USED' );
	if ( false !== $wp_cli_php_used ) {
		return $wp_cli_php_used;
	}

	$wp_cli_php = getenv( 'WP_CLI_PHP' );
	if ( false !== $wp_cli_php ) {
		return $wp_cli_php;
	}

	return PHP_BINARY;
}

function proc_open_compat( $cmd, $descriptorspec, &$pipes, $cwd = null, $env = null, $other_options = null ) {
	if ( is_windows() ) {
		$cmd = _proc_open_compat_win_env( $cmd, $env );
	}
	return proc_open( $cmd, $descriptorspec, $pipes, $cwd, $env, $other_options );
}

function _proc_open_compat_win_env( $cmd, &$env ) {
	if ( false !== strpos( $cmd, '=' ) ) {
		while ( preg_match( '/^([A-Za-z_][A-Za-z0-9_]*)=("[^"]*"|[^ ]*) /', $cmd, $matches ) ) {
			$cmd = substr( $cmd, strlen( $matches[0] ) );
			if ( null === $env ) {
				$env = [];
			}
			$env[ $matches[1] ] = isset( $matches[2][0] ) && '"' === $matches[2][0] ? substr( $matches[2], 1, -1 ) : $matches[2];
		}
	}
	return $cmd;
}

function esc_like( $text ) {
	global $wpdb;

	if ( null !== $wpdb && method_exists( $wpdb, 'esc_like' ) ) {
		return $wpdb->esc_like( $text );
	}

	return addcslashes( $text, '_%\\' );
}

function esc_sql_ident( $idents ) {
	$backtick = static function ( $v ) {
		return '`' . str_replace( '`', '``', $v ) . '`';
	};
	if ( is_string( $idents ) ) {
		return $backtick( $idents );
	}
	return array_map( $backtick, $idents );
}

function is_json( $argument, $ignore_scalars = true ) {
	if ( ! is_string( $argument ) || '' === $argument ) {
		return false;
	}

	if ( $ignore_scalars && ! in_array( $argument[0], [ '{', '[' ], true ) ) {
		return false;
	}

	json_decode( $argument, $assoc = true );

	return json_last_error() === JSON_ERROR_NONE;
}

function parse_shell_arrays( $assoc_args, $array_arguments ) {
	if ( empty( $assoc_args ) || empty( $array_arguments ) ) {
		return $assoc_args;
	}

	foreach ( $array_arguments as $key ) {
		if ( array_key_exists( $key, $assoc_args ) && is_json( $assoc_args[ $key ] ) ) {
			$assoc_args[ $key ] = json_decode( $assoc_args[ $key ], $assoc = true );
		}
	}

	return $assoc_args;
}

function describe_callable( $callable ) {
	try {
		if ( $callable instanceof Closure ) {
			$reflection = new ReflectionFunction( $callable );

			return "Closure in file {$reflection->getFileName()} at line {$reflection->getStartLine()}";
		}

		if ( is_array( $callable ) ) {
			if ( is_object( $callable[0] ) ) {
				return sprintf(
					'%s->%s()',
					get_class( $callable[0] ),
					$callable[1]
				);
			}

			return sprintf( '%s::%s()', $callable[0], $callable[1] );
		}

		return gettype( $callable );
	} catch ( Exception $exception ) {
		return 'Callable of unknown type';
	}
}

function is_valid_class_and_method_pair( $pair ) {
	if ( ! is_array( $pair ) || 2 !== count( $pair ) ) {
		return false;
	}

	if ( ! is_string( $pair[0] ) || ! is_string( $pair[1] ) ) {
		return false;
	}

	if ( ! class_exists( $pair[0] ) ) {
		return false;
	}

	if ( ! method_exists( $pair[0], $pair[1] ) ) {
		return false;
	}

	return true;
}

function pluralize( $noun, $count = null ) {
	if ( 1 === $count ) {
		return $noun;
	}

	return Inflector::pluralize( $noun );
}

function get_db_type() {
	static $db_type = null;

	if ( defined( 'SQLITE_DB_DROPIN_VERSION' ) ) {
		return 'sqlite';
	}

	if ( null !== $db_type ) {
		return $db_type;
	}

	$db_type = 'mysql';

	$binary = get_mysql_binary_path();

	if ( '' !== $binary ) {
		$result = Process::create( "$binary --version", null, null )->run();

		if ( 0 === $result->return_code ) {
			$db_type = ( false !== strpos( $result->stdout, 'MariaDB' ) ) ? 'mariadb' : 'mysql';
		}
	}

	return $db_type;
}

function get_mysql_binary_path() {
	static $path = null;

	if ( null !== $path ) {
		return $path;
	}

	$path    = '';
	$mysql   = Process::create( '/usr/bin/env which mysql', null, null )->run();
	$mariadb = Process::create( '/usr/bin/env which mariadb', null, null )->run();

	$mysql_binary   = trim( $mysql->stdout );
	$mariadb_binary = trim( $mariadb->stdout );

	if ( 0 === $mysql->return_code ) {
		if ( '' !== $mysql_binary ) {
			$path   = $mysql_binary;
			$result = Process::create( "$mysql_binary --version", null, null )->run();

			if ( 0 === $result->return_code && false !== strpos( $result->stdout, 'MariaDB' ) && 0 === $mariadb->return_code ) {
				$path = $mariadb_binary;
			}
		}
	} elseif ( 0 === $mariadb->return_code ) {
		$path = $mariadb_binary;
	}

	return $path;
}

function get_mysql_version() {
	static $version = null;

	if ( null !== $version ) {
		return $version;
	}

	$db_type = get_db_type();

	if ( 'sqlite' !== $db_type ) {
		$result = Process::create( "/usr/bin/env $db_type --version", null, null )->run();

		if ( 0 !== $result->return_code ) {
			$version = '';
		} else {
			$version = trim( $result->stdout );
		}
	}

	return $version;
}

function get_sql_dump_command() {
	return 'mariadb' === get_db_type() ? 'mariadb-dump' : 'mysqldump';
}

function get_sql_check_command() {
	return 'mariadb' === get_db_type() ? 'mariadb-check' : 'mysqlcheck';
}

function get_sql_modes() {
	static $sql_modes = null;

	if ( null !== $sql_modes ) {
		return $sql_modes;
	}

	$binary = get_mysql_binary_path();

	if ( '' === $binary ) {
		$sql_modes = [];
	} else {
		$result = Process::create( "$binary --no-auto-rehash --batch --skip-column-names --execute=\"SELECT @@SESSION.sql_mode\"", null, null )->run();

		if ( 0 !== $result->return_code ) {
			$sql_modes = [];
		} else {
			$sql_modes = array_filter(
				array_map(
					'trim',
					preg_split( "/\r\n|\n|\r/", $result->stdout )
				)
			);
		}
	}

	return $sql_modes;
}

function get_cache_dir() {
	$home = get_home_dir();
	return getenv( 'WP_CLI_CACHE_DIR' ) ? : "$home/.wp-cli/cache";
}

function has_stdin() {
	$handle  = fopen( 'php://stdin', 'r' );
	$read    = array( $handle );
	$write   = null;
	$except  = null;
	$streams = stream_select( $read, $write, $except, 0 );
	fclose( $handle );

	return 1 === $streams;
}

function get_hook_description( $hook ) {
	$events = [
		'find_command_to_run_pre'     => 'just before WP-CLI finds the command to run.',
		'before_registering_contexts' => 'before the contexts are registered.',
		'before_wp_load'              => 'just before the WP load process begins.',
		'before_wp_config_load'       => 'after wp-config.php has been located.',
		'after_wp_config_load'        => 'after wp-config.php has been loaded into scope.',
		'after_wp_load'               => 'just after the WP load process has completed.',
	];

	if ( array_key_exists( $hook, $events ) ) {
		return $events[ $hook ];
	}
	return null;
}

function escape_csv_value( $value ) {
	if ( null === $value ) {
		return '';
	}

	$value = (string) $value;

	if (
		in_array(
			substr( $value, 0, 1 ),
			[ '=', '+', '-', '@', "\t", "\r" ],
			true
		)
	) {
		return "'{$value}";
	}

	return $value;
}
