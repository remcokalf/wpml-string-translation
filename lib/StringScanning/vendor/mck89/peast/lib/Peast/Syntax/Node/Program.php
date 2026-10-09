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

use Peast\Query;
use Peast\Selector;

class Program extends Node
{
    protected $propertiesMap = array(
        "body" => true,
        "sourceType" => false
    );
    
    protected $sourceType = \Peast\Peast::SOURCE_TYPE_SCRIPT;
    
    protected $body = array();
    
    public function getSourceType()
    {
        return $this->sourceType;
    }
    
    public function setSourceType($sourceType)
    {
        $this->sourceType = $sourceType;
        return $this;
    }
    
    public function getBody()
    {
        return $this->body;
    }
    
    public function setBody($body)
    {
        $this->assertArrayOf($body, array("Statement", "ModuleDeclaration"));
        $this->body = $body;
        return $this;
    }

    public function query($selector, $options = array())
    {
        $query = new Query($this, $options);
        return $query->find($selector);
    }
}