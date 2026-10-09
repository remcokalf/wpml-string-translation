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

class AssignmentExpression extends Node implements Expression
{
    protected $propertiesMap = array(
        "left" => true,
        "operator" => false,
        "right" => true
    );
    
    protected $operator;
    
    protected $left;
    
    protected $right;
    
    public function getOperator()
    {
        return $this->operator;
    }
    
    public function setOperator($operator)
    {
        $this->operator = $operator;
        return $this;
    }
    
    public function getLeft()
    {
        return $this->left;
    }
    
    public function setLeft($left)
    {
        $this->assertType($left, array("Pattern", "Expression"));
        $this->left = $left;
        return $this;
    }
    
    public function getRight()
    {
        return $this->right;
    }
    
    public function setRight(Expression $right)
    {
        $this->right = $right;
        return $this;
    }
}