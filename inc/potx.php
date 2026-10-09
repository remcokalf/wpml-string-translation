<?php



define('POTX_STATUS_SILENT', 0);

define('POTX_STATUS_MESSAGE', 1);

define('POTX_STATUS_CLI', 2);

define('POTX_STATUS_STRUCTURED', 3);

define('POTX_BUILD_CORE', 0);

define('POTX_BUILD_MULTIPLE', 1);

define('POTX_BUILD_SINGLE', 2);

define('POTX_STRING_BOTH', 0);

define('POTX_STRING_INSTALLER', 1);

define('POTX_STRING_RUNTIME', 2);

define('POTX_API_5', 5);

define('POTX_API_6', 6);

define('POTX_API_7', 7);

define('POTX_CONTEXT_NONE', NULL);

define('POTX_CONTEXT_ERROR', FALSE);

function _potx_process_file($file_path,
							$strip_prefix = 0,
							$save_callback = '_potx_save_string',
							$version_callback = '_potx_save_version',
							$default_domain = '') {

  global $_potx_tokens, $_potx_lookup;

	if ( !wpml_st_file_path_is_valid( $file_path ) ) {
		return;
	}
  $code = file_get_contents($file_path);
  $file_name = $strip_prefix > 0 ? substr($file_path, $strip_prefix) : $file_path;
  _potx_find_version_number($code, $file_name, $version_callback);

  $raw_tokens = $code !== false ? token_get_all( $code ) : [];
  unset($code);

  $_potx_tokens = array();
  $_potx_lookup = array();
  $token_number = 0;
  $line_number = 1;
         $src_tokens = array(
            '__', 'esc_attr__', 'esc_html__', '_e', 'esc_attr_e', 'esc_html_e',
            '_x', 'esc_attr_x', 'esc_html_x', '_ex',
            '_n', '_nx'
         );
  foreach ($raw_tokens as $token) {
    if ((!is_array($token)) || (($token[0] != T_WHITESPACE) && ($token[0] != T_INLINE_HTML))) {
      if (is_array($token)) {
        $token[] = $line_number;

         if ($token[0] == T_STRING || ($token[0] == T_VARIABLE && in_array($token[1], $src_tokens))) {
           if (!isset($_potx_lookup[$token[1]])) {
             $_potx_lookup[$token[1]] = array();
           }
           $_potx_lookup[$token[1]][] = $token_number;
         }
      }
      $_potx_tokens[] = $token;
      $token_number++;
    }
    if (is_array($token)) {
      $line_number += count(explode("\n", $token[1])) - 1;
    }
    else {
      $line_number += count(explode("\n", $token)) - 1;
    }
  }
  unset($raw_tokens);

  foreach( $src_tokens as $tk ) {
    _potx_find_t_calls_with_context($file_name, $save_callback, $tk, $default_domain);
  }

}

function _potx_format_quoted_string($str) {
  $quo = substr($str, 0, 1);
  $str = substr($str, 1, -1);
  if ($quo == '"') {
    $str = stripcslashes($str);
  }
  else {
    $str = strtr($str, array("\\'" => "'", "\\\\" => "\\"));
  }
  return addcslashes($str, "\0..\37\\\"");
}

function wpml_potx_unquote_context_or_domain( $string ) {
	$quote_type = mb_substr( $string, 0, 1 );
	return trim( $string, $quote_type );
}

function _potx_marker_error($file, $line, $marker, $ti, $error, $docs_url = NULL) {
  global $_potx_tokens;

  $tokens = '';
  $ti += 2;
  $tc = count($_potx_tokens);
  $par = 1;
  while ((($tc - $ti) > 0) && $par) {
    if (is_array($_potx_tokens[$ti])) {
      $tokens .= $_potx_tokens[$ti][1];
    }
    else {
      $tokens .= $_potx_tokens[$ti];
      if ($_potx_tokens[$ti] == "(") {
        $par++;
      }
      else if ($_potx_tokens[$ti] == ")") {
        $par--;
      }
    }
    $ti++;
  }
  potx_status('error', $error, $file, $line, $marker .'('. $tokens, $docs_url);
}

function potx_status($op, $value = NULL, $file = NULL, $line = NULL, $excerpt = NULL, $docs_url = NULL) {
  static $mode = POTX_STATUS_CLI;
  static $messages = array();

  switch ($op) {
    case 'set':
      $mode = $value;
      return;

    case 'get':
      $errors = $messages;
      if (!empty($value)) {
        $messages = array();
      }
      return $errors;

    case 'error':
    case 'status':

      $location_info = '';
      if (($mode != POTX_STATUS_STRUCTURED) && isset($file)) {
        if (isset($line)) {
          if (isset($excerpt)) {
            $location_info = potx_t('At %excerpt in %file on line %line.', array('%excerpt' => $excerpt, '%file' => $file, '%line' => $line));
          }
          else {
            $location_info = potx_t('In %file on line %line.', array('%file' => $file, '%line' => $line));
          }
        }
        else {
          if (isset($excerpt)) {
            $location_info = potx_t('At %excerpt in %file.', array('%excerpt' => $excerpt, '%file' => $file));
          }
          else {
            $location_info = potx_t('In %file.', array('%file' => $file));
          }
        }
      }

      $read_more = '';
      if (($mode != POTX_STATUS_STRUCTURED) && isset($docs_url)) {
        $read_more = ($mode == POTX_STATUS_CLI) ? potx_t('Read more at @url', array('@url' => $docs_url)) : potx_t('Read more at <a href="@url">@url</a>', array('@url' => $docs_url));
      }

      switch ($mode) {
        case POTX_STATUS_CLI:
          if(defined('STDERR') && defined('STDOUT')){
            fwrite($op == 'error' ? STDERR : STDOUT, join("\n", array($value, $location_info, $read_more)) ."\n\n");
          }
          break;
        case POTX_STATUS_SILENT:
          if ($op == 'error') {
            $messages[] = join(' ', array($value, $location_info, $read_more));
          }
          break;
        case POTX_STATUS_STRUCTURED:
          if ($op == 'error') {
            $messages[] = array($value, $file, $line, $excerpt, $docs_url);
          }
          break;
      }
      return;
  }
}

function _potx_find_t_calls($file, $save_callback, $function_name = 't', $string_mode = POTX_STRING_RUNTIME) {
  global $_potx_tokens, $_potx_lookup;

  if (isset($_potx_lookup[$function_name])) {
    foreach ($_potx_lookup[$function_name] as $ti) {
      list($ctok, $par, $mid, $rig) = array($_potx_tokens[$ti], $_potx_tokens[$ti+1], $_potx_tokens[$ti+2], $_potx_tokens[$ti+3]);
      list($type, $string, $line) = $ctok;
      if ($par == "(") {
        if (in_array($rig, array(")", ","))
          && (is_array($mid) && ($mid[0] == T_CONSTANT_ENCAPSED_STRING))) {
            $save_callback(_potx_format_quoted_string($mid[1]), POTX_CONTEXT_NONE, $file, $line, $string_mode);
        }
        else {
          _potx_marker_error($file, $line, $function_name, $ti, potx_t('The first parameter to @function() should be a literal string. There should be no variables, concatenation, constants or other non-literal strings there.', array('@function' => $function_name)), 'http://drupal.org/node/322732');
        }
      }
    }
  }
}

function _potx_find_t_calls_with_context(
	$file,
	$save_callback,
	$function_name = '_e',
	$default_domain = '',
	$string_mode = POTX_STRING_RUNTIME
) {
	global $_potx_tokens, $_potx_lookup;

	$filter_by_domain = isset( $_GET['domain'] ) ? (string) \WPML\API\Sanitize::string( $_GET['domain']) : null;

	if ( isset( $_potx_lookup[ $function_name ] ) ) {
		foreach ( $_potx_lookup[ $function_name ] as $ti ) {
			list( $ctok, $par, $mid, $rig ) = array(
				$_potx_tokens[ $ti ],
				$_potx_tokens[ $ti + 1 ],
				$_potx_tokens[ $ti + 2 ],
				$_potx_tokens[ $ti + 3 ]
			);
			list( $type, $string, $line ) = $ctok;
			if ( $par == "(" ) {
				if ( in_array( $rig, array( ")", "," ) )
					 && ( is_array( $mid ) && ( $mid[ 0 ] == T_CONSTANT_ENCAPSED_STRING ) )
				) {
					$context = false;
					$domain = POTX_CONTEXT_NONE;
					if ( $rig == ',' ) {
						if ( in_array( $function_name, array( '_x', '_ex', 'esc_attr_x', 'esc_html_x' ), true ) ) {
							$domain_offset  = 6;
							$context_offset = 4;
						} elseif ( $function_name == '_n' ) {
							$domain_offset  = _potx_find_end_of_function( $ti, '(', ')' ) - 1 - $ti;
							$context_offset = false;
							$text_plural    = $_potx_tokens[ $ti + 4 ][ 1 ];
						} elseif ( $function_name == '_nx' ) {
							$domain_offset  = _potx_find_end_of_function( $ti, '(', ')' ) - 1 - $ti;
							$context_offset = $domain_offset - 2;
							$text_plural    = $_potx_tokens[ $ti + 4 ][ 1 ];
						} else {
							$domain_offset  = 4;
							$context_offset = false;
						}

						if ( ! isset( $_potx_tokens[ $ti + $domain_offset ][ 1 ] )
							 || ! preg_match( '#^(\'|")(.+)#', $_potx_tokens[ $ti + $domain_offset ][ 1 ] )
						) {
							if ( $default_domain ) {
								$domain = $default_domain;
							} else {
								continue;
							}
						} else {
							$domain = wpml_potx_unquote_context_or_domain( $_potx_tokens[ $ti + $domain_offset ][ 1 ] );
						}

						if ( false !== $context_offset && isset( $_potx_tokens[ $ti + $context_offset ] ) ) {
							if ( ! preg_match( '#^(\'|")(.+)#', @$_potx_tokens[ $ti + $context_offset ][ 1 ] ) ) {
								$constant_name = $_potx_tokens[ $ti + $context_offset ][ 1 ];
								if ( defined( $constant_name ) ) {
									$context = constant( $constant_name );
								} else {
									if ( function_exists( @$_potx_tokens[ $ti + $context_offset ][ 1 ] ) ) {
										$context = @$_potx_tokens[ $ti + $context_offset ][ 1 ]();
										if ( empty( $context ) ) {
											continue;
										}
									} else {
										continue;
									}
								}
							} else {
								$context = wpml_potx_unquote_context_or_domain( $_potx_tokens[ $ti + $context_offset ][ 1 ] );
							}

						} else {
							$context = false;
						}
					}
					if (
						$domain !== POTX_CONTEXT_ERROR &&
						( ! $filter_by_domain || $filter_by_domain === $domain ) &&
						is_callable( $save_callback, false, $callback_name )
					) {
						call_user_func( $save_callback,
										_potx_format_quoted_string( $mid[ 1 ] ),
										$domain,
										@strval( $context ),
										$file,
										$line,
										$string_mode );
						if ( isset( $text_plural ) ) {
							call_user_func( $save_callback,
											_potx_format_quoted_string( $text_plural ),
											$domain,
											$context,
											$file,
											$line,
											$string_mode );
						}
					}
				} else {
					_potx_marker_error( $file,
										$line,
										$function_name,
										$ti,
										potx_t( 'The first parameter to @function() should be a literal string. There should be no variables, concatenation, constants or other non-literal strings there.',
												array( '@function' => $function_name ) ),
										'http://drupal.org/node/322732' );
				}
			}
		}
	}
}

function _potx_find_end_of_function($here, $open = '{', $close = '}') {
  global $_potx_tokens;

  while (is_array($_potx_tokens[$here]) || $_potx_tokens[$here] != $open) {
    $here++;
  }
  $nesting = 1;
  while ($nesting > 0) {
    $here++;
    if (!is_array($_potx_tokens[$here])) {
      if ($_potx_tokens[$here] == $close) {
        $nesting--;
      }
      if ($_potx_tokens[$here] == $open) {
        $nesting++;
      }
    }
  }
  return $here;
}

function _potx_skip_args($here) {
  global $_potx_tokens;

  $nesting = 0;
  while (!(($_potx_tokens[$here] == ',' && $nesting == 0) ||
           ($_potx_tokens[$here] == ')' && $nesting == -1))) {
    $here++;
    if (!is_array($_potx_tokens[$here])) {
      if ($_potx_tokens[$here] == ')') {
        $nesting--;
      }
      if ($_potx_tokens[$here] == '(') {
        $nesting++;
      }
    }
  }
  return ($nesting == 0 ? $here : FALSE);
}

function _potx_find_context($tf, $ti, $file, $function_name) {
  global $_potx_tokens;

  if (($ti = _potx_skip_args($ti)) && ($_potx_tokens[$ti] == ',')) {
    echo "TI:" . $ti."\n";
    list($com, $arr, $par) = array($_potx_tokens[$ti], $_potx_tokens[$ti+1], $_potx_tokens[$ti+2]);
    if ($com == ',' && $arr[1] == 'array' && $par == '(') {
      $nesting = 0;
      $ti += 3;
      while (!((is_array($_potx_tokens[$ti]) && (in_array($_potx_tokens[$ti][1], array('"context"', "'context'"))) && ($_potx_tokens[$ti][0] == T_CONSTANT_ENCAPSED_STRING) && ($nesting == 0)) ||
               ($_potx_tokens[$ti] == ')' && $nesting == -1))) {
        $ti++;
        if (!is_array($_potx_tokens[$ti])) {
          if ($_potx_tokens[$ti] == ')') {
            $nesting--;
          }
          if ($_potx_tokens[$ti] == '(') {
            $nesting++;
          }
        }
      }
      if ($nesting == 0) {
        list($arw, $str) = array($_potx_tokens[$ti+1], $_potx_tokens[$ti+2]);
        if (is_array($arw) && $arw[1] == '=>' && is_array($str) && $str[0] == T_CONSTANT_ENCAPSED_STRING) {
          return _potx_format_quoted_string($str[1]);
        }
        else {
          list($type, $string, $line) = $_potx_tokens[$ti];
          _potx_marker_error($file, $line, $function_name, $tf, potx_t('The context element in the options array argument to @function() should be a literal string. There should be no variables, concatenation, constants or other non-literal strings there.', array('@function' => $function_name)), 'http://drupal.org/node/322732');
          return POTX_CONTEXT_ERROR;
        }
      }
      else {
        return POTX_CONTEXT_NONE;
      }
    }
  }

  return POTX_CONTEXT_NONE;
}

function _potx_find_version_number($code, $file, $version_callback) {
  if ( $code !== false && preg_match('!\\$I'.'d: ([^\\$]+) Exp \\$!', $code, $version_info)) {
    $version_callback($version_info[1], $file);
  }
  else {
    $version_callback($file .': n/a', $file);
  }
}

function _potx_save_version($value = NULL, $file = NULL) {
  global $_potx_versions;

  if (isset($value)) {
    $_potx_versions[$file] = $value;
  }
  else {
    return $_potx_versions;
  }
}

function _potx_save_string($value = NULL, $context = NULL, $file = NULL, $line = 0, $string_mode = POTX_STRING_RUNTIME) {
  global $_potx_strings, $_potx_install;

  if (isset($value)) {
    switch ($string_mode) {
      case POTX_STRING_BOTH:
        $_potx_install[$value][$context][$file][] = $line .' (dup)';
      case POTX_STRING_RUNTIME:
        $_potx_strings[$value][$context][$file][] = $line . ($string_mode == POTX_STRING_BOTH ? ' (dup)' : '');
        break;
      case POTX_STRING_INSTALLER:
        $_potx_install[$value][$context][$file][] = $line;
        break;
    }
  }
  else {
    return ($string_mode == POTX_STRING_RUNTIME ? $_potx_strings : $_potx_install);
  }
}

function potx_t( $string, $args = array() ) {

    return strtr ( $string, $args );
}
