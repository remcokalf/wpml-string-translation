<?php

namespace Gettext\Utils;

use Gettext\Extractors\PhpCode;

class PhpFunctionsScanner extends FunctionsScanner
{
    protected $tokens;

    protected $extractComments = false;

    public function enableCommentsExtraction($tag = '')
    {
        $this->extractComments = $tag;
    }

    public function disableCommentsExtraction()
    {
        $this->extractComments = false;
    }

    public function __construct($code)
    {
        $this->tokens = array_values(
            array_filter(
                token_get_all($code),
                function ($token) {
                    return !is_array($token) || $token[0] !== T_WHITESPACE;
                }
            )
        );
    }

    public function getFunctions(array $constants = [])
    {
        $count = count($this->tokens);
        $bufferFunctions = [];
        $bufferComments = [];
        $functions = [];

        for ($k = 0; $k < $count; ++$k) {
            $value = $this->tokens[$k];

            if (is_string($value)) {
                if (isset($bufferFunctions[0])) {
                    switch ($value) {
                        case ',':
                            $bufferFunctions[0]->nextArgument();
                            break;
                        case ')':
                            $functions[] = array_shift($bufferFunctions)->close();
                            break;
                        case '.':
                            break;
                        default:
                            $bufferFunctions[0]->stopArgument();
                            break;
                    }
                }
                continue;
            }

            if (defined('T_NAME_FULLY_QUALIFIED') && T_NAME_FULLY_QUALIFIED === $value[0]) {
                $value[0] = T_STRING;
                $value[1] = ltrim($value[1], '\\');
            }

            switch ($value[0]) {
                case T_CONSTANT_ENCAPSED_STRING:
                    if (isset($bufferFunctions[0])) {
                        $bufferFunctions[0]->addArgumentChunk(PhpCode::convertString($value[1]));
                    }
                    break;

                case T_STRING:
                    if (isset($bufferFunctions[0])) {
                        if (isset($constants[$value[1]])) {
                            $bufferFunctions[0]->addArgumentChunk($constants[$value[1]]);
                            break;
                        }

                        if (strtolower($value[1]) === 'null') {
                            $bufferFunctions[0]->addArgumentChunk(null);
                            break;
                        }

                        $bufferFunctions[0]->stopArgument();
                    }

                    for ($j = $k + 1; $j < $count; ++$j) {
                        $nextToken = $this->tokens[$j];

                        if (is_array($nextToken) && $nextToken[0] === T_COMMENT) {
                            continue;
                        }

                        if ($nextToken === '(') {
                            $newFunction = new ParsedFunction($value[1], $value[2]);

                            if (isset($bufferComments[0])) {
                                $comment = $bufferComments[0];

                                if ($comment->isRelatedWith($newFunction)) {
                                    $newFunction->addComment($comment);
                                }
                            }

                            array_unshift($bufferFunctions, $newFunction);
                            $k = $j;
                        }
                        break;
                    }
                    break;

                case T_COMMENT:
                    $comment = $this->parsePhpComment($value[1], $value[2]);

                    if ($comment) {
                        array_unshift($bufferComments, $comment);

                        if (isset($bufferFunctions[0])) {
                            $bufferFunctions[0]->addComment($comment);
                        }
                    }
                    break;

                default:
                    if (isset($bufferFunctions[0])) {
                        $bufferFunctions[0]->stopArgument();
                    }
                    break;
            }
        }

        return $functions;
    }

    protected function parsePhpComment($value, $line)
    {
        if ($this->extractComments === false) {
            return null;
        }

        $comment = ParsedComment::create($value, $line);

        $prefixes = array_filter((array) $this->extractComments);

        if ($comment && $comment->checkPrefixes($prefixes)) {
            return $comment;
        }

        return null;
    }
}
