<?php

namespace Gettext\Utils;

class ParsedFunction
{
    protected $name;

    protected $line;

    protected $arguments;

    protected $argumentIndex;

    protected $argumentStopped;

    protected $comments;

    public function __construct($name, $line)
    {
        $this->name = $name;
        $this->line = $line;
        $this->arguments = [];
        $this->argumentIndex = -1;
        $this->argumentStopped = false;
        $this->comments = null;
    }

    public function stopArgument()
    {
        if ($this->argumentIndex === -1) {
            $this->argumentIndex = 0;
        }
        $this->argumentStopped = true;
    }

    public function nextArgument()
    {
        if ($this->argumentIndex === -1) {
            $this->argumentIndex = 1;
        } else {
            ++$this->argumentIndex;
        }
        $this->argumentStopped = false;
    }

    public function addArgumentChunk($chunk)
    {
        if ($this->argumentStopped === false) {
            if ($this->argumentIndex === -1) {
                $this->argumentIndex = 0;
            }
            if (isset($this->arguments[$this->argumentIndex])) {
                $this->arguments[$this->argumentIndex] .= $chunk;
            } else {
                $this->arguments[$this->argumentIndex] = $chunk;
            }
        }
    }

    public function addComment($comment)
    {
        if ($this->comments === null) {
            $this->comments = [];
        }
        $this->comments[] = $comment;
    }

    public function getLine()
    {
        return $this->line;
    }

    public function close()
    {
        $arguments = [];
        for ($i = 0; $i <= $this->argumentIndex; ++$i) {
            $arguments[$i] = isset($this->arguments[$i]) ? $this->arguments[$i] : null;
        }

        return [
            $this->name,
            $this->line,
            $arguments,
            $this->comments,
        ];
    }
}
