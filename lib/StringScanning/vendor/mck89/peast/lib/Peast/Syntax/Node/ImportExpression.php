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

class ImportExpression extends Node implements Expression
{
    protected $propertiesMap = array(
        "source" => true,
        "options" => true
    );
    
    protected $source;
    
    protected $options;
    
    public function getSource()
    {
        return $this->source;
    }
    
    public function setSource(Expression $source)
    {
        $this->source = $source;
        return $this;
    }
    
    public function getOptions()
    {
        return $this->options;
    }
    
    public function setOptions($options)
    {
        $this->assertType($options, "Expression", true);
        $this->options = $options;
        return $this;
    }
}