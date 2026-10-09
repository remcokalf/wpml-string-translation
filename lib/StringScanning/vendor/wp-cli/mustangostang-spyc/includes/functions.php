<?php
/**
   * Spyc -- A Simple PHP YAML Class
   * @version 0.6.2
   * @author Vlad Andersen <vlad.andersen@gmail.com>
   * @author Chris Wanstrath <chris@ozmm.org>
   * @link https://github.com/mustangostang/spyc/
   * @copyright Copyright 2005-2006 Chris Wanstrath, 2006-2011 Vlad Andersen
   * @license http://www.opensource.org/licenses/mit-license.php MIT License
   * @package Spyc
   */

if (!function_exists('spyc_load')) {
  function spyc_load ($string) {
    return Spyc::YAMLLoadString($string);
  }
}

if (!function_exists('spyc_load_file')) {
  function spyc_load_file ($file) {
    return Spyc::YAMLLoad($file);
  }
}

if (!function_exists('spyc_dump')) {
  function spyc_dump ($data) {
    return Spyc::YAMLDump($data, false, false, true);
  }
}
