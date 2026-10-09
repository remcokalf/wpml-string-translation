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

class CatchClause extends Node
{
    protected $propertiesMap = array(
        "param" => true,
        "body" => true
    );
    
    protected $param;
    
    protected $body;
    
    public function getParam()
    {
        return $this->param;
    }
    
    public function setParam($param)
    {
        $this->assertType($param, "Pattern", true);
        $this->param = $param;
        return $this;
    }
    
    public function getBody()
    {
        return $this->body;
    }
    
    public function setBody(BlockStatement $body)
    {
        $this->body = $body;
        return $this;
    }
}