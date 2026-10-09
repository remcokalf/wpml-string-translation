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

class MetaProperty extends Node implements Expression
{
    protected $propertiesMap = array(
        "meta" => false,
        "property" => false
    );
    
    protected $meta;
    
    protected $property;
    
    public function getMeta()
    {
        return $this->meta;
    }
    
    public function setMeta($meta)
    {
        $this->meta = $meta;
        return $this;
    }
    
    public function getProperty()
    {
        return $this->property;
    }
    
    public function setProperty($property)
    {
        $this->property = $property;
        return $this;
    }
}