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
use Peast\Syntax\Node\Expression;

class JSXFragment extends Node implements Expression
{
    protected $propertiesMap = array(
        "openingFragment" => true,
        "children" => true,
        "closingFragment" => true
    );
    
    protected $openingFragment;
    
    protected $children = array();
    
    protected $closingFragment;
    
    public function getOpeningFragment()
    {
        return $this->openingFragment;
    }
    
    public function setOpeningFragment(JSXOpeningFragment $openingFragment)
    {
        $this->openingFragment = $openingFragment;
        return $this;
    }
    
    public function getChildren()
    {
        return $this->children;
    }
    
    public function setChildren($children)
    {
        $this->assertArrayOf($children, array(
            "JSX\\JSXText", "JSX\\JSXExpressionContainer", "JSX\\JSXSpreadChild",
            "JSX\\JSXElement", "JSX\\JSXFragment"
        ));
        $this->children = $children;
        return $this;
    }
    
    public function getClosingFragment()
    {
        return $this->closingFragment;
    }
    
    public function setClosingFragment(JSXClosingFragment $closingFragment)
    {
        $this->closingFragment = $closingFragment;
        return $this;
    }
}