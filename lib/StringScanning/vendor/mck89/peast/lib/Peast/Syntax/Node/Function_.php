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

abstract class Function_ extends Node
{
    protected $propertiesMap = array(
        "id" => true,
        "params" => true,
        "body" => true,
        "generator" => false,
        "async" => false
    );
    
    protected $id;
    
    protected $params = array();
    
    protected $body;
    
    protected $generator = false;
    
    protected $async = false;
    
    public function getId()
    {
        return $this->id;
    }
    
    public function setId($id)
    {
        $this->assertType($id, "Identifier", true);
        $this->id = $id;
        return $this;
    }
    
    public function getParams()
    {
        return $this->params;
    }
    
    public function setParams($params)
    {
        $this->assertArrayOf($params, "Pattern");
        $this->params = $params;
        return $this;
    }
    
    public function getBody()
    {
        return $this->body;
    }
    
    public function setBody($body)
    {
        $this->assertType($body, "BlockStatement");
        $this->body = $body;
        return $this;
    }
    
    public function getGenerator()
    {
        return $this->generator;
    }
    
    public function setGenerator($generator)
    {
        $this->generator = (bool) $generator;
        return $this;
    }
    
    public function getAsync()
    {
        return $this->async;
    }
    
    public function setAsync($async)
    {
        $this->async = (bool) $async;
        return $this;
    }
}