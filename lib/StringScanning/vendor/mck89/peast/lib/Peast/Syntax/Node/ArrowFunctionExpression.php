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

class ArrowFunctionExpression extends Function_ implements Expression
{
    protected $propertiesMap = array(
        "expression" => false
    );
    
    protected $expression = false;
    
    public function setBody($body)
    {
        $this->assertType($body, array("BlockStatement", "Expression"));
        $this->body = $body;
        return $this;
    }
    
    public function getExpression()
    {
        return $this->expression;
    }
    
    public function setExpression($expression)
    {
        $this->expression = (bool) $expression;
        return $this;
    }
}