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

class JSXText extends Node
{
    protected $propertiesMap = array(
        "value" => false,
        "raw" => false
    );
    
    protected $value;
    
    protected $raw;
    
    public function getValue()
    {
        return $this->value;
    }
    
    public function setValue($value)
    {
        $this->value = $value;
        $this->raw = $value;
        return $this;
    }
    
    public function getRaw()
    {
        return $this->raw;
    }
    
    public function setRaw($raw)
    {
        return $this->setValue($raw);
    }
}