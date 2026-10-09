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

class Type extends Part
{
    protected $type;

    public function setType($type)
    {
        $this->type = $type;
        return $this;
    }

    public function check(Node $node, $parent = null)
    {
        return $node->getType() === $this->type;
    }
}