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

class ObjectExpression extends Node implements Expression
{
    protected $propertiesMap = array(
        "properties" => true
    );
    
    protected $properties = array();
    
    public function getProperties()
    {
        return $this->properties;
    }
    
    public function setProperties($properties)
    {
        $this->assertArrayOf($properties, array("Property", "SpreadElement"));
        $this->properties = $properties;
        return $this;
    }
}