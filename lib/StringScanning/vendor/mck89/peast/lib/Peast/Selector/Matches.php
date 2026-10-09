<?php
/**
 * This file is part of the Peast package
 *
 * (c) Marco Marchiò <marco.mm89@gmail.com>
 *
 * For the full copyright and license information refer to the LICENSE file
 * distributed with this source code
 */
namespace Peast\Selector;

use Peast\Syntax\Node\Node;

class Matches
{
    protected $matches;

    public function __construct($matches = array())
    {
        $this->matches = $matches;
    }

    public function addMatch(Node $node, $parent = null)
    {
        $this->matches[] = array($node, $parent);
    }

    public function getMatches()
    {
        return $this->matches;
    }

    public function getNodes() {
        return array_map(function ($m) {
            return $m[0];
        }, $this->matches);
    }

    public function filter(callable $fn)
    {
        $newMatches = array();
        foreach ($this->matches as $match) {
            if ($fn($match[0], $match[1])) {
                $newMatches[] = $match;
            }
        }
        $this->matches = $newMatches;
        return $this;
    }

    public function map(callable $fn)
    {
        $newMatches = array();
        foreach ($this->matches as $match) {
            $res = $fn($match[0], $match[1]);
            if ($res) {
                $newMatches = array_merge($newMatches, $res);
            }
        }
        $this->matches = $newMatches;
        return $this->unique();
    }

    public function merge($matchesArr)
    {
        foreach ($matchesArr as $matches) {
            foreach ($matches->getMatches() as $match) {
                $this->addMatch($match[0], $match[1]);
            }
        }
        return $this->unique();
    }

    public function unique()
    {
        $newMatches = array();
        $newNodes = array();
        foreach ($this->matches as $match) {
            if (!in_array($match[0], $newNodes, true)) {
                $newMatches[] = $match;
                $newNodes[] = $match[0];
            }
        }
        $this->matches = $newMatches;
        return $this;
    }

    public function createClone()
    {
        return new self($this->matches);
    }

    public function count()
    {
        return count($this->matches);
    }

    public function get($index)
    {
        $index = (int) $index;
        if (!isset($this->matches[$index])) {
            throw new \Exception("Invalid index $index");
        }
        return $this->matches[$index];
    }
}