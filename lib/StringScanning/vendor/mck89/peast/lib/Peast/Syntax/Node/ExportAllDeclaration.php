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

class ExportAllDeclaration extends Node implements ModuleDeclaration
{
    protected $propertiesMap = array(
        "source" => true,
        "exported" => true,
        "attributes" => true
    );

    protected $source;

    protected $exported;
    
    protected $attributes = array();

    public function getSource()
    {
        return $this->source;
    }

    public function setSource(Literal $source)
    {
        $this->source = $source;
        return $this;
    }

    public function getExported()
    {
        return $this->exported;
    }

    public function setExported($exported)
    {
        $this->assertType($exported, array("Identifier", "StringLiteral"), true);
        $this->exported = $exported;
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