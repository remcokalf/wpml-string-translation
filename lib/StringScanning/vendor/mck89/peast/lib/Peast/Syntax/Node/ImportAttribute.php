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

class ImportAttribute extends Node
{
    protected $propertiesMap = array(
        "key" => true,
        "value" => true
    );
    
    protected $key;
    
    protected $value;
    
    public function getKey()
    {
        return $this->key;
    }
    
    public function setKey($key)
    {
        $this->assertType($key, array("Identifier", "Literal"));
        $this->key = $key;
        return $this;
    }
    
    public function getValue()
    {
        return $this->value;
    }
    
    public function setValue(Literal $value)
    {
        $this->value = $value;
        return $this;
    }
}