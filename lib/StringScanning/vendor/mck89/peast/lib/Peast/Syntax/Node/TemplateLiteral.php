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

class TemplateLiteral extends Node implements Expression
{
    protected $propertiesMap = array(
        "parts" => true,
        "quasis" => false,
        "expressions" => false
    );
    
    protected $quasis = array();
    
    protected $expressions = array();
    
    public function getQuasis()
    {
        return $this->quasis;
    }
    
    public function setQuasis($quasis)
    {
        $this->assertArrayOf($quasis, "TemplateElement");
        $this->quasis = $quasis;
        return $this;
    }
    
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
    
    public function getParts()
    {
        $parts = array();
        foreach ($this->quasis as $k => $val) {
            $parts[] = $val;
            if (isset($this->expressions[$k])) {
                $parts[] = $this->expressions[$k];
            }
        }
        return $parts;
    }
    
    public function setParts($parts)
    {
        $this->assertArrayOf($parts, array("Expression", "TemplateElement"));
        $quasis = $expressions = array();
        foreach ($parts as $part) {
            if ($part instanceof TemplateElement) {
                $quasis[] = $part;
            } else {
                $expressions[] = $part;
            }
        }
        return $this->setQuasis($quasis)->setExpressions($expressions);
    }
    
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        $ret = parent::jsonSerialize();
        unset($ret["parts"]);
        return $ret;
    }
}