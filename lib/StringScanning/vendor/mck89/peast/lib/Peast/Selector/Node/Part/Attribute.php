<?php
/**
 * This file is part of the Peast package
 *
 * (c) Marco Marchiò <marco.mm89@gmail.com>
 *
 * For the full copyright and license information refer to the LICENSE file
 * distributed with this source code
 */
namespace Peast\Selector\Node\Part;

use Peast\Syntax\Node\Node;
use Peast\Syntax\Utils;

class Attribute extends Part
{
    protected $priority = 4;

    protected $names = array();

    protected $operator = null;

    protected $value = null;

    protected $caseInsensitive = false;

    protected $regex = false;

    public function addName($name)
    {
        $this->names[] = $name;
        return $this;
    }

    public function setOperator($operator)
    {
        $this->operator = $operator;
        return $this;
    }

    public function setValue($value)
    {
        $this->value = $value;
        return $this;
    }

    public function setCaseInsensitive($caseInsensitive)
    {
        $this->caseInsensitive = $caseInsensitive;
        return $this;
    }

    public function setRegex($regex)
    {
        $this->regex = $regex;
        return $this;
    }

    public function check(Node $node, $parent = null)
    {
        $attr = $node;
        foreach ($this->names as $name) {
            $attrFound = false;
            if ($attr instanceof Node) {
                $props = Utils::getNodeProperties($attr);
                foreach ($props as $prop) {
                    if ($prop["name"] === $name) {
                        $attrFound = true;
                        $attr = $attr->{$prop["getter"]}();
                        break;
                    }
                }
            }
            if (!$attrFound) {
                return false;
            }
        }
        $bothStrings = is_string($attr) && is_string($this->value);
        switch ($this->operator) {
            case "=":
                if ($bothStrings) {
                    if ($this->regex) {
                        return preg_match($this->value, $attr);
                    }
                    return $this->compareStr(
                        $this->value, $attr, $this->caseInsensitive, true, true
                    );
                }
                if (is_int($attr) && is_float($this->value)) {
                    return (float) $attr === $this->value;
                }
                return $attr === $this->value;
            case "<":
                if (is_float($this->value) && !is_float($attr) && !is_int($attr) && !is_string($attr)) {
                    return false;
                }
                return $attr < $this->value;
            case ">":
                if (is_float($this->value) && !is_float($attr) && !is_int($attr) && !is_string($attr)) {
                    return false;
                }
                return $attr > $this->value;
            case "<=":
                if (is_float($this->value) && !is_float($attr) && !is_int($attr) && !is_string($attr)) {
                    return false;
                }
                return $attr <= $this->value;
            case ">=":
                if (is_float($this->value) && !is_float($attr) && !is_int($attr) && !is_string($attr)) {
                    return false;
                }
                return $attr >= $this->value;
            case "^=":
            case "$=":
            case "*=":
                return $this->compareStr(
                    $this->value, $attr, $this->caseInsensitive,
                    $this->operator === "^=",
                    $this->operator === "$="
                );
            default:
                return true;
        }
    }

    protected function compareStr($v1, $v2, $caseInsensitive, $matchStart, $matchEnd)
    {
        $regex = "#" .
                 ($matchStart ? "^" : "") .
                 preg_quote($v1) .
                 ($matchEnd ? "$" : "") .
                 "#u" .
                 ($caseInsensitive ? "i" : "");
        return (bool) preg_match($regex, $v2);
    }
}