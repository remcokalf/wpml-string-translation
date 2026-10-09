<?php

namespace WP_CLI;

class ProcessRun {

	public $command;

	public $stdout;

	public $stderr;

	public $cwd;

	public $env;

	public $return_code;

	public $run_time;

	public function __construct( $props ) {
		foreach ( $props as $key => $value ) {
			$this->$key = $value;
		}
	}

	public function __toString() {
		$out  = "$ $this->command\n";
		$out .= "$this->stdout\n$this->stderr";
		$out .= "cwd: $this->cwd\n";
		$out .= "run time: $this->run_time\n";
		$out .= "exit status: $this->return_code";

		return $out;
	}
}
