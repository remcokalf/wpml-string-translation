<?php

namespace Gettext\Utils;

class StringReader
{
    public $pos;
    public $str;
    public $strlen;

    public function __construct($str)
    {
        $this->pos = 0;
        $this->str = $str;
        $this->strlen = strlen($this->str);
    }

    public function read($bytes)
    {
        $data = substr($this->str, $this->pos, $bytes);

        $this->seekto($this->pos + $bytes);

        return $data;
    }

    public function seekto($pos)
    {
        $this->pos = ($this->strlen < $pos) ? $this->strlen : $pos;

        return $this->pos;
    }
}
