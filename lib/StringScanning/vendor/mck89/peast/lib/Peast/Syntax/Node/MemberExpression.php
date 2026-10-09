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

class MemberExpression extends ChainElement implements Pattern
{
    protected $propertiesMap = array(
        "object" => true,
        "property" => true,
        "computed" => false
    );
    
    protected $object;
    
    protected $property;
    
    protected $computed = false;
    
    public function getObject()
    {
        return $this->object;
    }
    
    public function setObject($object)
    {
        $this->assertType($object, array("Expression", "Super"));
        $this->object = $object;
        return $this;
    }
    
    public function getProperty()
    {
        return $this->property;
    }
    
    public function setProperty($property)
    {
        $this->assertType($property, array("Expression", "PrivateIdentifier"));
        $this->property = $property;
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
}