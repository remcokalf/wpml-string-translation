<?php
/**
 * This file is part of the Peast package
 *
 * (c) Marco Marchiò <marco.mm89@gmail.com>
 *
 * For the full copyright and license information refer to the LICENSE file
 * distributed with this source code
 */
namespace Peast\Syntax\Node;

class NullLiteral extends Literal
{
    protected $value = null;
    
    protected $raw = "null";
    
    public function setValue($value)
    {
        return $this;
    }
}