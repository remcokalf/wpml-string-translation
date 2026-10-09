<?php
/**
 * This file is part of the Peast package
 *
 * (c) Marco Marchiò <marco.mm89@gmail.com>
 *
 * For the full copyright and license information refer to the LICENSE file
 * distributed with this source code
 */
namespace Peast;

class Query implements \IteratorAggregate, \Countable
{
    protected $matches;

    protected $options;

    public function __construct(Syntax\Node\Program $root, $options = array())
    {
        $this->matches = new Selector\Matches();
        $this->matches->addMatch($root);
        $this->options = $options;
    }

    public function find($selector)
    {
        $parser = new Selector\Parser($selector, $this->options);
        $selector = $parser->parse();
        $this->matches = $selector->exec($this->matches);
        return $this;
    }

    public function filter($selector)
    {
        $parser = new Selector\Parser($selector, $this->options);
        $selector = $parser->parse(true);
        $this->matches->filter(function ($node, $parent) use ($selector) {
            $newMatch = new Selector\Matches();
            $newMatch->addMatch($node, $parent);
            return $selector->exec($newMatch)->getMatches();
        });
        return $this;
    }

    #[\ReturnTypeWillChange]
    public function count()
    {
        return $this->matches->count();
    }

    public function get($index)
    {
        return $this->matches->get($index)[0];
    }

    #[\ReturnTypeWillChange]
    public function getIterator()
    {
        return new \ArrayIterator($this->matches->getNodes());
    }
}