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

class PropertyDefinition extends Node
{
    protected $propertiesMap = array(
        "key" => true,
        "value" => true,
        "computed" => false,
        "static" => false
    );
    
    protected $key;
    
    protected $value;
    
    protected $computed = false;
    
    protected $static = false;
    
    public function getKey()
    {
        return $this->key;
    }
    
    public function setKey($key)
    {
        $this->assertType($key, array("Expression", "PrivateIdentifier"));
        $this->key = $key;
        return $this;
    }
    
    public function getValue()
    {
        return $this->value;
    }
    
    public function setValue($value)
    {
        $this->assertType($value, "Expression", true);
        $this->value = $value;
        return $this;
    }
    
    public function getComputed()
    {
        return $this->computed;
    }
    
    public function setComputed($computed)
    {
        $this->computed = (bool) $computed;
        return $this;
    }
    
    public function getStatic()
    {
        return $this->static;
    }
    
    public function setStatic($static)
    {
        $this->static = (bool) $static;
        return $this;
    }
}