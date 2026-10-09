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

class BreakStatement extends Node implements Statement
{
    protected $propertiesMap = array(
        "label" => true
    );
    
    protected $label;
    
    public function getLabel()
    {
        return $this->label;
    }
    
    public function setLabel($label)
    {
        $this->assertType($label, "Identifier", true);
        $this->label = $label;
        return $this;
    }
}