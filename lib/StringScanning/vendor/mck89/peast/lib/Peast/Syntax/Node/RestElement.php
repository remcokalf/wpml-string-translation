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

class RestElement extends Node implements Pattern
{
    protected $propertiesMap = array(
        "argument" => true
    );
    
    protected $argument;
    
    public function getArgument()
    {
        return $this->argument;
    }
    
    public function setArgument(Pattern $argument)
    {
        $this->argument = $argument;
        return $this;
    }
}