<?php

namespace WP_CLI\I18n;

use Gettext\Utils\JsFunctionsScanner as GettextJsFunctionsScanner;
use Gettext\Utils\ParsedComment;
use Peast\Peast;
use Peast\Syntax\Node;
use Peast\Traverser;

final class JsFunctionsScanner extends GettextJsFunctionsScanner {

	private $extract_comments = false;

	private $comments_cache = [];

	public function enableCommentsExtraction( $tag = '' ) {
		$this->extract_comments = $tag;
	}

	public function disableCommentsExtraction() {
		$this->extract_comments = false;
	}

	public function saveGettextFunctions( $translations, array $options ) {
		if ( is_array( $translations ) ) {
			$translations = $translations[0];
		}

		$peast_options = [
			'sourceType' => Peast::SOURCE_TYPE_MODULE,
			'comments'   => false !== $this->extract_comments,
			'jsx'        => true,
		];
		$ast           = Peast::latest( $this->code, $peast_options )->parse();

		$traverser = new Traverser();

		$all_comments = [];

		$traverser->addFunction(
			function ( $node ) use ( &$translations, $options, &$all_comments ) {
				$functions     = $options['functions'];
				$file          = $options['file'];
				$add_reference = ! empty( $options['addReferences'] );

				foreach ( $node->getLeadingComments() as $comment ) {
					$all_comments[] = $comment;
				}

				if ( 'CallExpression' !== $node->getType() ) {
					return;
				}

				$callee = $this->resolveExpressionCallee( $node );

				if ( ! $callee || ! isset( $functions[ $callee['name'] ] ) ) {
					return;
				}

				foreach ( $node->getArguments() as $argument ) {
					$argument->setLeadingComments( $argument->getLeadingComments() + $node->getLeadingComments() );
				}

				foreach ( $callee['comments'] as $comment ) {
					$all_comments[] = $comment;
				}

				$domain   = null;
				$original = null;
				$context  = null;
				$plural   = null;
				$args     = [];

				foreach ( $node->getArguments() as $argument ) {
					foreach ( $argument->getLeadingComments() as $comment ) {
						$all_comments[] = $comment;
					}

					if (
						'Identifier' === $argument->getType() ||
						'Expression' === substr( $argument->getType(), -strlen( 'Expression' ) )
					) {
						$args[] = '';
						continue;
					}

					if ( 'Literal' === $argument->getType() ) {
						$args[] = $argument->getValue();
						continue;
					}

					if ( 'TemplateLiteral' === $argument->getType() && 0 === count( $argument->getExpressions() ) ) {

						$parts  = $argument->getParts();
						$args[] = $parts[0]->getValue();
						continue;
					}

					return;
				}

				switch ( $functions[ $callee['name'] ] ) {
					case 'text_domain':
					case 'gettext':
						list( $original, $domain ) = array_pad( $args, 2, null );
						break;

					case 'text_context_domain':
						list( $original, $context, $domain ) = array_pad( $args, 3, null );
						break;

					case 'single_plural_number_domain':
						list( $original, $plural, $number, $domain ) = array_pad( $args, 4, null );
						break;

					case 'single_plural_number_context_domain':
						list( $original, $plural, $number, $context, $domain ) = array_pad( $args, 5, null );
						break;
				}

				if ( '' === (string) $original ) {
					return;
				}

				if ( $domain !== $translations->getDomain() && null !== $translations->getDomain() ) {
					return;
				}

				if ( isset( $options['line'] ) ) {
					$line = $options['line'];
				} else {
					$line = $node->getLocation()->getStart()->getLine();
				}

				$translation = $translations->insert( $context, $original, $plural );

				if ( $add_reference ) {
					$translation->addReference( $file, $line );
				}

				if (
					1 === preg_match( MakePotCommand::SPRINTF_PLACEHOLDER_REGEX, $original ) ||
					1 === preg_match( MakePotCommand::UNORDERED_SPRINTF_PLACEHOLDER_REGEX, $original )
				) {
					$translation->addFlag( 'js-format' );
				}

				foreach ( $all_comments as $comment ) {
					if ( ! $this->commentPrecedesNode( $comment, $node ) ) {
						continue;
					}

					if ( in_array( $comment, $this->comments_cache, true ) ) {
						continue;
					}

					$parsed_comment = ParsedComment::create( $comment->getRawText(), $comment->getLocation()->getStart()->getLine() );
					$prefixes       = array_filter( (array) $this->extract_comments );

					if ( $parsed_comment->checkPrefixes( $prefixes ) ) {
						$translation->addExtractedComment( $parsed_comment->getComment() );

						$this->comments_cache[] = $comment;
					}
				}

				if ( isset( $parsed_comment ) ) {
					$all_comments = [];
				}
			}
		);

		$scanner = $this;
		$traverser->addFunction(
			function ( $node ) use ( &$translations, $options, $scanner ) {
				if ( 'CallExpression' !== $node->getType() ) {
					return;
				}

				$callee = $this->resolveExpressionCallee( $node );

				if ( ! $callee || 'eval' !== $callee['name'] ) {
					return;
				}

				$eval_contents = '';
				foreach ( $node->getArguments() as $argument ) {
					if ( 'Literal' === $argument->getType() ) {
						$eval_contents = $argument->getValue();
						break;
					}
				}

				if ( ! $eval_contents ) {
					return;
				}

				$options['line'] = $node->getLocation()->getStart()->getLine();

				$class = get_class( $scanner );
				$evals = new $class( $eval_contents );
				$evals->enableCommentsExtraction( $options['extractComments'] );
				$evals->saveGettextFunctions( $translations, $options );
			}
		);

		$traverser->traverse( $ast );
	}

	private function resolveExpressionCallee( Node\CallExpression $node ) {
		$callee = $node->getCallee();

		if ( 'Identifier' === $callee->getType() ) {
			return [
				'name'     => $callee->getName(),
				'comments' => $callee->getLeadingComments(),
			];
		}

		if (
			'MemberExpression' === $callee->getType() &&
			'Identifier' === $callee->getProperty()->getType()
		) {
			$comments = 'MemberExpression' === $callee->getObject()->getType()
				? $callee->getObject()->getObject()->getLeadingComments()
				: $callee->getObject()->getLeadingComments();

			return [
				'name'     => $callee->getProperty()->getName(),
				'comments' => $comments,
			];
		}

		if (
			'CallExpression' === $callee->getType() &&
			'Identifier' === $callee->getCallee()->getType() &&
			'Object' === $callee->getCallee()->getName() &&
			[] !== $callee->getArguments() &&
			'MemberExpression' === $callee->getArguments()[0]->getType()
		) {
			$property = $callee->getArguments()[0]->getProperty();

			if ( 'Identifier' === $property->getType() ) {
				return [
					'name'     => $property->getName(),
					'comments' => $callee->getCallee()->getLeadingComments(),
				];
			}

			if ( 'Literal' === $property->getType() ) {
				$name = $property->getValue();

				$leading_property_comments = $property->getLeadingComments();
				if ( count( $leading_property_comments ) === 1 && $leading_property_comments[0]->getKind() === 'multiline' ) {
					$name = trim( $leading_property_comments[0]->getText() );
				}

				return [
					'name'     => $name,
					'comments' => $callee->getCallee()->getLeadingComments(),
				];
			}
		}

		if (
			'ParenthesizedExpression' === $callee->getType()
			&& 'SequenceExpression' === $callee->getExpression()->getType()
			&& 2 === count( $callee->getExpression()->getExpressions() )
			&& 'Literal' === $callee->getExpression()->getExpressions()[0]->getType()
			&& [] !== $node->getArguments()
		) {
			if ( 'Identifier' === $callee->getExpression()->getExpressions()[1]->getType() ) {
				return [
					'name'     => $callee->getExpression()->getExpressions()[1]->getName(),
					'comments' => $callee->getLeadingComments(),
				];
			}

			if ( 'MemberExpression' === $callee->getExpression()->getExpressions()[1]->getType() ) {
				$property = $callee->getExpression()->getExpressions()[1]->getProperty();

				if ( 'Identifier' === $property->getType() ) {
					return [
						'name'     => $property->getName(),
						'comments' => $callee->getLeadingComments(),
					];
				}
			}
		}

		return false;
	}

	private function commentPrecedesNode( Node\Comment $comment, Node\Node $node ) {
		if ( $node->getLocation()->getStart()->getLine() - $comment->getLocation()->getEnd()->getLine() > 1 ) {
			return false;
		}

		if (
			$node->getLocation()->getStart()->getLine() === $comment->getLocation()->getEnd()->getLine() &&
			$node->getLocation()->getStart()->getColumn() < $comment->getLocation()->getStart()->getColumn()
		) {
			return false;
		}

		return true;
	}
}
