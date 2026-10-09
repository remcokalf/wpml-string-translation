<?php
/**
 * This file is part of the Peast package
 *
 * (c) Marco Marchiò <marco.mm89@gmail.com>
 *
 * For the full copyright and license information refer to the LICENSE file
 * distributed with this source code
 */
namespace Peast;

class Traverser
{
    const REMOVE_NODE = 1;
    
    const DONT_TRAVERSE_CHILD_NODES = 2;
    
    const STOP_TRAVERSING = 4;
    
    protected $functions = array();

    protected $passParentNode = false;

    protected $skipStartingNode = false;

    public function __construct($options = array())
    {
        if (isset($options["passParentNode"])) {
            $this->passParentNode = (bool) $options["passParentNode"];
        }
        if (isset($options["skipStartingNode"])) {
            $this->skipStartingNode = (bool) $options["skipStartingNode"];
        }
    }
    
    public function addFunction(callable $fn)
    {
        $this->functions[] = $fn;
        return $this;
    }
    
    public function traverse(Syntax\Node\Node $node)
    {
        if ($this->skipStartingNode) {
            $this->traverseChildren($node);
        } else {
            $this->execFunctions($node);
        }
        return $node;
    }
    
    protected function execFunctions($node, $parent = null)
    {
        $traverseChildren = true;
        $continueTraversing = true;
        
        foreach ($this->functions as $fn) {
            $ret = $this->passParentNode ? $fn($node, $parent) : $fn($node);
            if ($ret) {
                if (is_array($ret) && $ret[0] instanceof Syntax\Node\Node) {
                    $node = $ret[0];
                    if (isset($ret[1]) && is_numeric($ret[1])) {
                        if ($ret[1] & self::DONT_TRAVERSE_CHILD_NODES) {
                            $traverseChildren = false;
                        }
                        if ($ret[1] & self::STOP_TRAVERSING) {
                            $continueTraversing = false;
                        }
                    }
                } elseif ($ret instanceof Syntax\Node\Node) {
                    $node = $ret;
                } elseif (is_numeric($ret)) {
                    if ($ret & self::DONT_TRAVERSE_CHILD_NODES) {
                        $traverseChildren = false;
                    }
                    if ($ret & self::STOP_TRAVERSING) {
                        $continueTraversing = false;
                    }
                    if ($ret & self::REMOVE_NODE) {
                        $node = null;
                        $traverseChildren = false;
                        break;
                    }
                }
            }
        }
        
        if ($traverseChildren && $continueTraversing) {
            $continueTraversing = $this->traverseChildren($node);
        }
        
        return array($node, $continueTraversing);
    }
    
    protected function traverseChildren(Syntax\Node\Node $node)
    {
        $continue = true;
        
        foreach (Syntax\Utils::getNodeProperties($node, true) as $prop) {
            $getter = $prop["getter"];
            $setter = $prop["setter"];
            $child = $node->$getter();
            if (!$child) {
                continue;
            } elseif (is_array($child)) {
                $newChildren = array();
                foreach ($child as $c) {
                    if (!$c || !$continue) {
                        $newChildren[] = $c;
                    } else {
                        list($c, $continue) = $this->execFunctions($c, $node);
                        if ($c) {
                            $newChildren[] = $c;
                        }
                    }
                }
                $node->$setter($newChildren);
            } else {
                list($child, $continue) = $this->execFunctions($child, $node);
                $node->$setter($child);
            }
            
            if (!$continue) {
                break;
            }
        }
        
        return $continue;
    }
}