<?php

namespace WP_CLI\Dispatcher;

use Closure;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use WP_CLI;
use WP_CLI\DocParser;
use WP_CLI\Utils;

class CommandFactory {

	private static $file_contents = [];

	public static function create( $name, $callable, $parent ) {

		if ( ( is_object( $callable ) && ( $callable instanceof Closure ) )
			|| ( is_string( $callable ) && function_exists( $callable ) ) ) {
			$reflection = new ReflectionFunction( $callable );
			$command    = self::create_subcommand( $parent, $name, $callable, $reflection );
		} elseif ( is_array( $callable ) && ( is_callable( $callable ) || Utils\is_valid_class_and_method_pair( $callable ) ) ) {
			$reflection = new ReflectionClass( $callable[0] );
			$command    = self::create_subcommand(
				$parent,
				$name,
				[ $callable[0], $callable[1] ],
				$reflection->getMethod( $callable[1] )
			);
		} else {
			$reflection = new ReflectionClass( $callable );
			if ( $reflection->isSubclassOf( '\WP_CLI\Dispatcher\CommandNamespace' ) ) {
				$command = self::create_namespace( $parent, $name, $callable );
			} elseif ( $reflection->hasMethod( '__invoke' ) ) {
				$class   = is_object( $callable ) ? $callable : $reflection->name;
				$command = self::create_subcommand(
					$parent,
					$name,
					[ $class, '__invoke' ],
					$reflection->getMethod( '__invoke' )
				);
			} else {
				$command = self::create_composite_command( $parent, $name, $callable );
			}
		}

		return $command;
	}

	public static function clear_file_contents_cache() {
		self::$file_contents = [];
	}

	private static function create_subcommand( $parent, $name, $callable, $reflection ) {
		$doc_comment = self::get_doc_comment( $reflection );
		$docparser   = new DocParser( $doc_comment );

		if ( is_array( $callable ) ) {
			if ( ! $name ) {
				$name = $docparser->get_tag( 'subcommand' );
			}

			if ( ! $name ) {
				$name = $reflection->name;
			}
		}
		if ( ! $doc_comment ) {
			WP_CLI::debug( null === $doc_comment ? "Failed to get doc comment for {$name}." : "No doc comment for {$name}.", 'commandfactory' );
		}

		$when_invoked = function ( $args, $assoc_args ) use ( $callable ) {
			if ( is_array( $callable ) ) {
				$callable[0] = is_object( $callable[0] ) ? $callable[0] : new $callable[0]();
				call_user_func( [ $callable[0], $callable[1] ], $args, $assoc_args );
			} else {
				call_user_func( $callable, $args, $assoc_args );
			}
		};

		return new Subcommand( $parent, $name, $docparser, $when_invoked );
	}

	private static function create_composite_command( $parent, $name, $callable ) {
		$reflection  = new ReflectionClass( $callable );
		$doc_comment = self::get_doc_comment( $reflection );
		if ( ! $doc_comment ) {
			WP_CLI::debug( null === $doc_comment ? "Failed to get doc comment for {$name}." : "No doc comment for {$name}.", 'commandfactory' );
		}
		$docparser = new DocParser( $doc_comment );

		$container = new CompositeCommand( $parent, $name, $docparser );

		foreach ( $reflection->getMethods() as $method ) {
			if ( ! self::is_good_method( $method ) ) {
				continue;
			}

			$class      = is_object( $callable ) ? $callable : $reflection->name;
			$subcommand = self::create_subcommand( $container, false, [ $class, $method->name ], $method );

			$subcommand_name = $subcommand->get_name();

			$container->add_subcommand( $subcommand_name, $subcommand );
		}

		return $container;
	}

	private static function create_namespace( $parent, $name, $callable ) {
		$reflection  = new ReflectionClass( $callable );
		$doc_comment = self::get_doc_comment( $reflection );
		if ( ! $doc_comment ) {
			WP_CLI::debug( null === $doc_comment ? "Failed to get doc comment for {$name}." : "No doc comment for {$name}.", 'commandfactory' );
		}
		$docparser = new DocParser( $doc_comment );

		return new CommandNamespace( $parent, $name, $docparser );
	}

	private static function is_good_method( $method ) {
		return $method->isPublic() && ! $method->isStatic() && 0 !== strpos( $method->getName(), '__' );
	}

	private static function get_doc_comment( $reflection ) {
		$contents    = null;
		$doc_comment = $reflection->getDocComment();

		if ( false !== $doc_comment || ! ( ini_get( 'opcache.enable_cli' ) && ! ini_get( 'opcache.save_comments' ) ) ) {
			if ( ! getenv( 'WP_CLI_TEST_GET_DOC_COMMENT' ) ) {
				return $doc_comment;
			}
		}

		$filename = $reflection->getFileName();

		if ( isset( self::$file_contents[ $filename ] ) ) {
			$contents = self::$file_contents[ $filename ];
		} elseif ( is_readable( $filename ) ) {
			$contents = file_get_contents( $filename );
			if ( is_string( $contents ) && '' !== $contents ) {
				$contents                         = explode( "\n", $contents );
				self::$file_contents[ $filename ] = $contents;
			}
		}

		if ( ! empty( $contents ) ) {
			return self::extract_last_doc_comment( implode( "\n", array_slice( $contents, 0, $reflection->getStartLine() ) ) );
		}

		WP_CLI::debug( "Could not read contents for filename '{$filename}'.", 'commandfactory' );
		return null;
	}

	private static function extract_last_doc_comment( $content ) {
		$content         = trim( $content );
		$comment_end_pos = strrpos( $content, '*/' );

		if ( false === $comment_end_pos ) {
			return false;
		}

		if ( preg_match_all( '/(?:^|[\s;}])(?:class|function)\s+/', substr( $content, $comment_end_pos + 2 ), $dummy   ) > 1 ) {
			return false;
		}

		$content           = substr( $content, 0, $comment_end_pos + 2 );
		$comment_start_pos = strrpos( $content, '/**' );

		if ( false === $comment_start_pos || ( $comment_start_pos + 2 ) === $comment_end_pos ) {
			return false;
		}

		$comment_end2_pos = strpos( substr( $content, $comment_start_pos ), '*/' );

		if ( false !== $comment_end2_pos && ( $comment_start_pos + $comment_end2_pos ) < $comment_end_pos ) {
			return false;
		}

		$subcontent         = substr( $content, 0, $comment_start_pos );
		$comment_start2_pos = strrpos( $subcontent, '/**' );

		while ( false !== $comment_start2_pos && false === strpos( $subcontent, '*/', $comment_start2_pos ) ) {
			$comment_start_pos  = $comment_start2_pos;
			$subcontent         = substr( $subcontent, 0, $comment_start_pos );
			$comment_start2_pos = strrpos( $subcontent, '/**' );
		}

		return substr( $content, $comment_start_pos, $comment_end_pos + 2 );
	}
}
