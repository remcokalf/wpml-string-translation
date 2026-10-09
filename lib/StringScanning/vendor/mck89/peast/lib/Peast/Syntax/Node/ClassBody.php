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

class ClassBody extends Node
{
    protected $propertiesMap = array(
        "body" => true
    );
    
    protected $body = array();
    
    public function getBody()
    {
        return $this->body;
    }
    
    public function setBody($body)
    {
        $this->assertArrayOf(
            $body,
            array("MethodDefinition", "PropertyDefinition", "StaticBlock")
        );
        $this->body = $body;
        return $this;
    }
}