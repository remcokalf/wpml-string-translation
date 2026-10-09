<?php

namespace Gettext\Utils;

class ParsedComment
{
    protected $comment;

    protected $firstLine;

    protected $lastLine;

    public function __construct($comment, $firstLine, $lastLine)
    {
        $this->comment = $comment;
        $this->firstLine = $firstLine;
        $this->lastLine = $lastLine;
    }

    public static function create($value, $line)
    {
        $lastLine = $line + substr_count($value, "\n");

        $lines = array_map(function ($line) {
            if ('' === trim($line)) {
                return null;
            }

            $line = ltrim($line, "#*/ \t");
            $line = rtrim($line, "#*/ \t");

            return trim($line);
        }, explode("\n", $value));

        $lines = array_filter($lines);
        $value = implode(' ', $lines);

        return new static($value, $line, $lastLine);
    }

    public function getFirstLine()
    {
        return $this->firstLine;
    }

    public function getLastLine()
    {
        return $this->lastLine;
    }

    public function getComment()
    {
        return $this->comment;
    }

    public function isRelatedWith(ParsedFunction $function)
    {
        return $this->getLastLine() === $function->getLine() || $this->getLastLine() === $function->getLine() - 1;
    }

    public function checkPrefixes(array $prefixes)
    {
        if ('' === $this->comment) {
            return false;
        }

        if (empty($prefixes)) {
            return true;
        }

        foreach ($prefixes as $prefix) {
            if (strpos($this->comment, $prefix) === 0) {
                return true;
            }
        }

        return false;
    }
}
