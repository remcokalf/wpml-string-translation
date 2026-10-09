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
use Peast\Syntax\Node\Expression;

class JSXElement extends Node implements Expression
{
    protected $propertiesMap = array(
        "openingElement" => true,
        "children" => true,
        "closingElement" => true
    );
    
    protected $openingElement;
    
    protected $children = array();
    
    protected $closingElement;
    
    public function getOpeningElement()
    {
        return $this->openingElement;
    }
    
    public function setOpeningElement(JSXOpeningElement $openingElement)
    {
        $this->openingElement = $openingElement;
        return $this;
    }
    
    public function getChildren()
    {
        return $this->children;
    }
    
    public function setChildren($children)
    {
        $this->assertArrayOf($children, array(
            "JSX\\JSXText", "JSX\\JSXExpressionContainer", "JSX\\JSXSpreadChild",
            "JSX\\JSXElement", "JSX\\JSXFragment"
        ));
        $this->children = $children;
        return $this;
    }
    
    public function getClosingElement()
    {
        return $this->closingElement;
    }
    
    public function setClosingElement($closingElement)
    {
        $this->assertType($closingElement, "JSX\\JSXClosingElement", true);
        $this->closingElement = $closingElement;
        return $this;
    }
}