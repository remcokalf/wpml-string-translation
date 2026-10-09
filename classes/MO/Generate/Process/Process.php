<?php

namespace WPML\ST\MO\Generate\Process;


interface Process {

	public function runAll();

	public function runPage();

	public function getPagesCount();

	public function isCompleted();
}