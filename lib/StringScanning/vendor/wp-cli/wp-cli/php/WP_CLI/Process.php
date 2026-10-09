<?php

namespace WP_CLI;

use RuntimeException;

class Process {
	private $command;

	private $cwd;

	private $env;

	private static $descriptors = [
		0 => STDIN,
		1 => [ 'pipe', 'w' ],
		2 => [ 'pipe', 'w' ],
	];

	public static $log_run_times = false;

	public static $run_times = [];

	public static function create( $command, $cwd = null, $env = [] ) {
		$proc = new self();

		$proc->command = $command;
		$proc->cwd     = $cwd;
		$proc->env     = $env;

		return $proc;
	}

	private function __construct() {}

	public function run() {
		Utils\check_proc_available( 'Process::run' );

		$start_time = microtime( true );

		$pipes = [];
		$proc  = Utils\proc_open_compat( $this->command, self::$descriptors, $pipes, $this->cwd, $this->env );

		$stdout = stream_get_contents( $pipes[1] );
		fclose( $pipes[1] );

		$stderr = stream_get_contents( $pipes[2] );
		fclose( $pipes[2] );

		$return_code = proc_close( $proc );

		$run_time = microtime( true ) - $start_time;

		if ( self::$log_run_times ) {
			if ( ! isset( self::$run_times[ $this->command ] ) ) {
				self::$run_times[ $this->command ] = [ 0, 0 ];
			}
			self::$run_times[ $this->command ][0] += $run_time;
			++self::$run_times[ $this->command ][1];
		}

		return new ProcessRun(
			[
				'stdout'      => $stdout,
				'stderr'      => $stderr,
				'return_code' => $return_code,
				'command'     => $this->command,
				'cwd'         => $this->cwd,
				'env'         => $this->env,
				'run_time'    => $run_time,
			]
		);
	}

	public function run_check() {
		$r = $this->run();

		if ( $r->return_code ) {
			throw new RuntimeException( $r );
		}

		return $r;
	}

	public function run_check_stderr() {
		$r = $this->run();

		if ( $r->return_code ) {
			throw new RuntimeException( $r );
		}

		if ( ! empty( $r->stderr ) ) {
			$stderr_lines = array_filter( explode( "\n", $r->stderr ) );
			if ( 1 === count( $stderr_lines ) ) {
				$stderr_line = $stderr_lines[0];
				if (
					false !== strpos(
						$stderr_line,
						'The PSR-0 `Requests_...` class names in the Request library are deprecated.'
					)
				) {
					return $r;
				}
			}

			throw new RuntimeException( $r );
		}

		return $r;
	}
}
