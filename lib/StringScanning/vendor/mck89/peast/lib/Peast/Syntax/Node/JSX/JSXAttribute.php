<?php
/**
 * This file is part of the Peast package
 *
 * (c) Marco Marchiò <marco.mm89@gmail.com>
 *
 * For the full copyright and license information refer to the LICENSE file
 * distributed with this source code
 */
namespace Peast\Syntax\Node\JSX;

use Peast\Syntax\Node\Node;

class JSXAttribute extends Node
{
    protected $propertiesMap = array(
        "name" => true,
        "value" => true
    );
    
    protected $name;
    
    protected $value;
    
    public function getName()
    {
        return $this->name;
    }
    
    public function setName($name)
    {
        $this->assertType($name, array("JSX\\JSXIdentifier", "JSX\\JSXNamespacedName"));
        $this->name = $name;
        return $this;
    }
    
    public function getValue()
    {
        return $this->value;
    }
    
    public function setValue($value)
    {
        $this->assertType(
            $value,
            array(
                "Literal", "JSX\\JSXExpressionContainer",
                "JSX\\JSXElement", "JSX\\JSXFragment"
            ),
            true
        );
        $this->value = $value;
        return $this;
    }
}