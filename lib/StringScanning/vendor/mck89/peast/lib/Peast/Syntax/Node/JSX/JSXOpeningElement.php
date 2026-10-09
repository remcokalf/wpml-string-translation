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

class JSXOpeningElement extends JSXBoundaryElement
{
    protected $propertiesMap = array(
        "attributes" => true,
        "selfClosing" => false
    );
    
    protected $attributes = array();
    
    protected $selfClosing = false;
    
    public function getAttributes()
    {
        return $this->attributes;
    }
    
    public function setAttributes($attributes)
    {
        $this->assertArrayOf($attributes, array(
            "JSX\\JSXAttribute", "JSX\\JSXSpreadAttribute"
        ));
        $this->attributes = $attributes;
        return $this;
    }
    
    public function getSelfClosing()
    {
        return $this->selfClosing;
    }
    
    public function setSelfClosing($selfClosing)
    {
        $this->selfClosing = (bool) $selfClosing;
        return $this;
    }
}