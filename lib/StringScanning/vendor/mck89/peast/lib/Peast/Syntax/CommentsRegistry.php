<?php
/**
 * This file is part of the Peast package
 *
 * (c) Marco Marchiò <marco.mm89@gmail.com>
 *
 * For the full copyright and license information refer to the LICENSE file
 * distributed with this source code
 */
namespace Peast\Syntax;

class CommentsRegistry
{
    protected $nodesStartMap = array();
    
    protected $nodesEndMap = array();
    
    protected $buffer = null;
    
    protected $lastTokenIndex = null;
    
    protected $registry = array();
    
    public function __construct(Parser $parser)
    {
        $parser->getEventsEmitter()
               ->addListener("NodeCompleted", array($this, "onNodeCompleted"))
               ->addListener("EndParsing", array($this, "onEndParsing"));
        
        $parser->getScanner()->getEventsEmitter()
               ->addListener("TokenConsumed", array($this, "onTokenConsumed"))
               ->addListener("EndReached", array($this, "onTokenConsumed"))
               ->addListener("FreezeState", array($this, "onScannerFreezeState"))
               ->addListener("ResetState", array($this, "onScannerResetState"));
    }
    
    public function onScannerFreezeState(&$state)
    {
        $state["commentsLastTokenIndex"] = $this->lastTokenIndex;
    }
    
    public function onScannerResetState(&$state)
    {
        $this->lastTokenIndex = $state["commentsLastTokenIndex"];
        unset($state["commentsLastTokenIndex"]);
    }
    
    public function onTokenConsumed($token = null)
    {
        if ($token && $token->type === Token::TYPE_COMMENT) {
            if (!$this->buffer) {
                $this->buffer = array(
                    "prev" => $this->lastTokenIndex,
                    "next" => null,
                    "comments" => array()
                );
            }
            $this->buffer["comments"][] = $token;
        } else {
            
            if ($token) {
                $loc = $token->location;
                $this->lastTokenIndex = $loc->end->getIndex();
                if ($this->buffer) {
                    $this->buffer["next"] = $loc->start->getIndex();
                }
            }
            
            if ($buffer = $this->buffer) {
                $key = implode("-", array(
                    $buffer["prev"] !== null ? $buffer["prev"] : "",
                    $buffer["next"] !== null ? $buffer["next"] : ""
                ));
                $this->registry[$key] = $this->buffer;
                $this->buffer = null;
            }
            
        }
    }
    
    public function onNodeCompleted(Node\Node $node)
    {
        $loc = $node->location;
        foreach (array("Start", "End") as $pos) {
            $val = $loc->{"get$pos"}()->getIndex();
            $map = &$this->{"nodes{$pos}Map"};
            if (!isset($map[$val])) {
                $map[$val] = array();
            }
            $map[$val][] = $node;
        }
    }
    
    public function onEndParsing()
    {
        if ($this->registry) {
            
            ksort($this->nodesStartMap);
            
            foreach ($this->registry as $group) {
                $this->findNodeForCommentsGroup($group);
            }
        }
    }
    
    public function findNodeForCommentsGroup($group)
    {
        $next = $group["next"];
        $prev = $group["prev"];
        $comments = $group["comments"];
        $leading = true;
        
        if (isset($this->nodesStartMap[$next])) {
            $nodes = $this->nodesStartMap[$next];
        }
        elseif (isset($this->nodesEndMap[$prev])) {
            $nodes = $this->nodesEndMap[$prev];
            $leading = false;
        }
        else {
            $start = $comments[0]->location->start->getIndex();
            $end = $comments[count($comments) -1]->location->end->getIndex();
            $nodes = array();
            
            foreach ($this->nodesStartMap as $idx => $ns) {
                if ($idx > $start) {
                    break;
                }
                foreach ($ns as $node) {
                    if ($node->location->end->getIndex() >= $end) {
                        $nodes[] = $node;
                    }
                }
            }
            
            if (!$nodes) {
                $firstNode = array_values($this->nodesStartMap);
                $nodes = array($firstNode[0][0]);
            }
        }
        
        if (count($nodes) > 1) {
            usort($nodes, array($this, "compareNodesLength"));
        }
        $this->associateComments($nodes[0], $comments, $leading);
    }
    
    public function compareNodesLength($node1, $node2)
    {
        $loc1 = $node1->location;
        $length1 = $loc1->end->getIndex() - $loc1->start->getIndex();
        $loc2 = $node2->location;
        $length2 = $loc2->end->getIndex() - $loc2->start->getIndex();
        if ($length1 === $length2) {
            if ($node1 instanceof Node\Program) {
                $length1 += 1000;
            } elseif ($node2 instanceof Node\Program) {
                $length2 += 1000;
            }
        }
        return $length1 < $length2 ? -1 : 1;
    }
    
    public function associateComments($node, $comments, $leading)
    {
        $fn = ($leading ? "Leading" : "Trailing") . "Comments";
        $currentComments = $node->{"get$fn"}();
        foreach ($comments as $comment) {
            $loc = $comment->location;
            $commentNode = new Node\Comment;
            $commentNode->location->start = $loc->start;
            $commentNode->location->end = $loc->end;
            $commentNode->setRawText($comment->value);
            $currentComments[] = $commentNode;
        }
        $node->{"set$fn"}($currentComments);
    }
}