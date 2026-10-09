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

class ArrayPattern extends Node implements Pattern
{
    protected $propertiesMap = array(
        "elements" => true
    );
    
    protected $elements = array();
    
    public function getElements()
    {
        return $this->elements;
    }
    
    public function setElements($elements)
    {
        $this->assertArrayOf($elements, "Pattern", true);
        $this->elements = $elements;
        return $this;
    }
}