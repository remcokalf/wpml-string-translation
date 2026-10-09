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

class BigIntLiteral extends Literal
{
    protected $propertiesMap = array(
        "bigint" => false
    );
    
    protected $bigint;
    
    public function setValue($value)
    {
        $this->value = $this->raw = $this->bigint = $value;
        return $this;
    }
    
    public function getBigint()
    {
        return $this->bigint;
    }
    
    public function setBigint($bigint)
    {
        return $this->setValue($bigint);
    }
}