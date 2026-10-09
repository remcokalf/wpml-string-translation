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

abstract class Literal extends Node implements Expression
{
    protected $propertiesMap = array(
        "value" => false,
        "raw" => false
    );
    
    protected $value;
    
    protected $raw;
    
    public function getType()
    {
        return "Literal";
    }
    
    public function getValue()
    {
        return $this->value;
    }
    
    abstract public function setValue($value);
    
    public function getRaw()
    {
        return $this->raw;
    }
    
    public function setRaw($raw)
    {
        return $this->setValue($raw);
    }
}