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

class JSXExpressionContainer extends Node
{
    protected $propertiesMap = array(
        "expression" => true
    );
    
    protected $expression;
    
    public function getExpression()
    {
        return $this->expression;
    }
    
    public function setExpression($expression)
    {
        $this->assertType(
            $expression,
            array("Expression", "JSX\\JSXEmptyExpression")
        );
        $this->expression = $expression;
        return $this;
    }
}