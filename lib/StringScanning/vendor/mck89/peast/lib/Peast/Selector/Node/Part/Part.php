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

abstract class Part
{
    protected $priority = 5;

    public function getPriority()
    {
        return $this->priority;
    }

    abstract public function check(Node $node, $parent = null);
}