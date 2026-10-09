<?php
/**
 * This file is part of the Peast package
 *
 * (c) Marco Marchiò <marco.mm89@gmail.com>
 *
 * For the full copyright and license information refer to the LICENSE file
 * distributed with this source code
 */
namespace Peast\Selector\Node;

use Peast\Selector\Matches;

class Group
{
    protected $combinators = array();

    public function addCombinator(Combinator $combinators)
    {
        $this->combinators[] = $combinators;
        return $this;
    }

    public function exec(Matches $matches)
    {
        foreach ($this->combinators as $combinator) {
            $combinator->exec($matches);
        }
    }
}