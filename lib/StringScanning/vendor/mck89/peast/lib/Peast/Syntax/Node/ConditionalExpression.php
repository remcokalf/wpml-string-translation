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

class ConditionalExpression extends Node implements Expression
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
    
    public function setConsequent(Expression $consequent)
    {
        $this->consequent = $consequent;
        return $this;
    }
    
    public function getAlternate()
    {
        return $this->alternate;
    }
    
    public function setAlternate(Expression $alternate)
    {
        $this->alternate = $alternate;
        return $this;
    }
}