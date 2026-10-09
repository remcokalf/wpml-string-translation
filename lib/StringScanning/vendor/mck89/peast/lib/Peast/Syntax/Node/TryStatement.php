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

class TryStatement extends Node implements Statement
{
    protected $propertiesMap = array(
        "block" => true,
        "handler" => true,
        "finalizer" => true
    );
    
    protected $block;
    
    protected $handler;
    
    protected $finalizer;
    
    public function getBlock()
    {
        return $this->block;
    }
    
    public function setBlock(BlockStatement $block)
    {
        $this->block = $block;
        return $this;
    }
    
    public function getHandler()
    {
        return $this->handler;
    }
    
    public function setHandler($handler)
    {
        $this->assertType($handler, "CatchClause", true);
        $this->handler = $handler;
        return $this;
    }
    
    public function getFinalizer()
    {
        return $this->finalizer;
    }
    
    public function setFinalizer($finalizer)
    {
        $this->assertType($finalizer, "BlockStatement", true);
        $this->finalizer = $finalizer;
        return $this;
    }
}
