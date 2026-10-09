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

class SequenceExpression  extends Node implements Expression
{
    protected $propertiesMap = array(
        "expressions" => true
    );
    
    protected $expressions = array();
    
    public function getExpressions()
    {
        return $this->expressions;
    }
    
    public function setExpressions($expressions)
    {
        $this->assertArrayOf($expressions, "Expression");
        $this->expressions = $expressions;
        return $this;
    }
}