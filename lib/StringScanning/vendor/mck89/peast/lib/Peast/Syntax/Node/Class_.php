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

abstract class Class_ extends Node
{
    protected $propertiesMap = array(
        "id" => true,
        "superClass" => true,
        "body" => true
    );
    
    protected $id;
    
    protected $superClass;
    
    protected $body;
    
    public function getId()
    {
        return $this->id;
    }
    
    public function setId($id)
    {
        $this->assertType($id, "Identifier", true);
        $this->id = $id;
        return $this;
    }
    
    public function getSuperClass()
    {
        return $this->superClass;
    }
    
    public function setSuperClass($superClass)
    {
        $this->assertType($superClass, "Expression", true);
        $this->superClass = $superClass;
        return $this;
    }
    
    public function getBody()
    {
        return $this->body;
    }
    
    public function setBody($body)
    {
        $this->assertType($body, "ClassBody");
        $this->body = $body;
        return $this;
    }
}