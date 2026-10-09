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

class AssignmentPattern extends Node implements Pattern
{
    protected $propertiesMap = array(
        "left" => true,
        "right" => true
    );
    
    protected $left;
    
    protected $right;
    
    public function getLeft()
    {
        return $this->left;
    }
    
    public function setLeft(Pattern $left)
    {
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