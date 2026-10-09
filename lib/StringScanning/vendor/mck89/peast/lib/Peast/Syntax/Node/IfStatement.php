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

class IfStatement extends Node implements Statement
{
    protected $propertiesMap = array(
        "test" => true,
        "consequent" => true,
        "alternate" => true
    );
    
    protected $test;
    
    protected $consequent;
    
    protected $alternate;
    
    public function getTest()
    {
        return $this->test;
    }
    
    public function setTest(Expression $test)
    {
        $this->test = $test;
        return $this;
    }
    
    public function getConsequent()
    {
        return $this->consequent;
    }
    
    public function setConsequent($consequent)
    {
        $this->assertType(
            $consequent,
            array("Statement", "FunctionDeclaration"),
            true
        );
        $this->consequent = $consequent;
        return $this;
    }
    
    public function getAlternate()
    {
        return $this->alternate;
    }
    
    public function setAlternate($alternate)
    {
        $this->assertType(
            $alternate,
            array("Statement", "FunctionDeclaration"),
            true
        );
        $this->alternate = $alternate;
        return $this;
    }
}