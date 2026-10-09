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

class JSXNamespacedName extends Node implements Expression
{
    protected $propertiesMap = array(
        "namespace" => false,
        "name" => false
    );
    
    protected $namespace;
    
    protected $name;
    
    public function getNamespace()
    {
        return $this->namespace;
    }
    
    public function setNamespace($namespace)
    {
        $this->namespace = $namespace;
        return $this;
    }
    
    public function getName()
    {
        return $this->name;
    }
    
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }
}