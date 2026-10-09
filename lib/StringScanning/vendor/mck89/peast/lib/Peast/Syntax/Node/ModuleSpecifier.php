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

abstract class ModuleSpecifier extends Node
{
    protected $propertiesMap = array(
        "local" => true
    );
    
    protected $local;
    
    public function getLocal()
    {
        return $this->local;
    }
    
    public function setLocal($local)
    {
        $this->assertType($local, array("Identifier", "StringLiteral"));
        $this->local = $local;
        return $this;
    }
}