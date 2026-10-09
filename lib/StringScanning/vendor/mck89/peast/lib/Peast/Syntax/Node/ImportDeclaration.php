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

class ImportDeclaration extends Node implements ModuleDeclaration
{
    protected $propertiesMap = array(
        "specifiers" => true,
        "source" => true,
        "attributes" => true
    );
    
    protected $specifiers = array();
    
    protected $source;
    
    protected $attributes = array();
    
    public function getSpecifiers()
    {
        return $this->specifiers;
    }
    
    public function setSpecifiers($specifiers)
    {
        $this->assertArrayOf(
            $specifiers,
            array(
                "ImportSpecifier",
                "ImportDefaultSpecifier",
                "ImportNamespaceSpecifier"
            )
        );
        $this->specifiers = $specifiers;
        return $this;
    }
    
    public function getSource()
    {
        return $this->source;
    }
    
    public function setSource(Literal $source)
    {
        $this->source = $source;
        return $this;
    }
    
    public function getAttributes()
    {
        return $this->attributes;
    }
    
    public function setAttributes($attributes)
    {
        $this->assertArrayOf($attributes, "ImportAttribute");
        $this->attributes = $attributes;
        return $this;
    }
}