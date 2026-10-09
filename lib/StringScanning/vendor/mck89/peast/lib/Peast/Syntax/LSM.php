<?php
/**
 * This file is part of the Peast package
 *
 * (c) Marco Marchiò <marco.mm89@gmail.com>
 *
 * For the full copyright and license information refer to the LICENSE file
 * distributed with this source code
 */
namespace Peast\Syntax;

class LSM
{
    protected $map = array();
    
    protected $handleEncoding = false;
    
    function __construct($sequences, $handleEncoding = false)
    {
        $this->handleEncoding = $handleEncoding;
        foreach ($sequences as $s) {
            $this->add($s);
        }
    }
    
    public function add($sequence)
    {
        if ($this->handleEncoding) {
            $s = Utils::stringToUTF8Array($sequence);
            $first = $s[0];
            $len = count($s);
        } else {    
            $first = $sequence[0];
            $len = strlen($sequence);
        }
        if (!isset($this->map[$first])) {
            $this->map[$first] = array(
                "maxLen" => $len,
                "map" => array($sequence)
            );
        } else {
            $this->map[$first]["map"][] = $sequence;
            $this->map[$first]["maxLen"] = max($this->map[$first]["maxLen"], $len);
        }
        return $this;
    }
    
    public function remove($sequence)
    {
        if ($this->handleEncoding) {
            $s = Utils::stringToUTF8Array($sequence);
            $first = $s[0];
        } else {
            $first = $sequence[0];
        }
        if (isset($this->map[$first])) {
            $len = $this->handleEncoding ? count($s) : strlen($sequence);
            $this->map[$first]["map"] = array_diff(
                $this->map[$first]["map"], array($sequence)
            );
            if (!count($this->map[$first]["map"])) {
                unset($this->map[$first]);
            } elseif ($this->map[$first]["maxLen"] === $len) {
                foreach ($this->map[$first]["map"] as $m) {
                    $this->map[$first]["maxLen"] = max(
                        $this->map[$first]["maxLen"],
                        strlen($m)
                    );
                }
            }
        }
        return $this;
    }
    
    public function match($scanner, $index, $char)
    {
        $consumed = 1;
        $bestMatch = null;
        if (isset($this->map[$char])) {
            if ($this->map[$char]["maxLen"] === 1) {
                $bestMatch = array($consumed, $char);
            } else {
                $buffer = $char;
                $map = $this->map[$char]["map"];
                $maxLen = $this->map[$char]["maxLen"];
                do {
                    if (in_array($buffer, $map)) {
                        $bestMatch = array($consumed, $buffer);
                    }
                    $nextChar = $scanner->charAt($index + $consumed);
                    if ($nextChar === null) {
                        break;
                    }
                    $buffer .= $nextChar;
                    $consumed++;
                } while ($consumed <= $maxLen);
            }
        }
        return $bestMatch;
    }
}