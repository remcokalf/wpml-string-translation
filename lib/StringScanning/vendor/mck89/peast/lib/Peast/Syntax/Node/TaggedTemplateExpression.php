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

class TaggedTemplateExpression extends Node implements Expression
{
    protected $propertiesMap = array(
        "tag" => true,
        "quasi" => true
    );
    
    protected $tag;
    
    protected $quasi;
    
    public function getTag()
    {
        return $this->tag;
    }
    
    public function setTag(Expression $tag)
    {
        $this->tag = $tag;
        return $this;
    }
    
    public function getQuasi()
    {
        return $this->quasi;
    }
    
    public function setQuasi(TemplateLiteral $quasi)
    {
        $this->quasi = $quasi;
        return $this;
    }
}