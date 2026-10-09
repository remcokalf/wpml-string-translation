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

abstract class ParserAbstract
{
    protected $scanner;

    protected $features;
    
    protected $context;
    
    protected $sourceType;
    
    protected $comments;
    
    protected $jsx;
    
    protected $eventsEmitter;

    public function __construct(
        $source, Features $features, $options = array()
    ) {
        $this->features = $features;
        
        $this->sourceType = isset($options["sourceType"]) ?
                            $options["sourceType"] :
                            \Peast\Peast::SOURCE_TYPE_SCRIPT;
        
        $this->scanner = new Scanner($source, $features, $options);
        
        if ($this->sourceType === \Peast\Peast::SOURCE_TYPE_MODULE) {
            $this->scanner->enableModuleMode();
        }
        
        $this->comments = isset($options["comments"]) && $options["comments"];
        if ($this->comments) {
            $this->scanner->enableComments();
            new CommentsRegistry($this);
        }
        
        $this->jsx = isset($options["jsx"]) && $options["jsx"];
        
        $this->initContext();
        $this->postInit();
    }
    
    abstract protected function initContext();
    
    abstract protected function postInit();
    
    abstract public function parse();
    
    public function tokenize()
    {
        $this->scanner->enableTokenRegistration();
        $this->parse();
        return $this->scanner->getTokens();
    }
    
    public function getScanner()
    {
        return $this->scanner;
    }
    
    public function getFeatures()
    {
        return $this->features;
    }
    
    public function getEventsEmitter()
    {
        if (!$this->eventsEmitter) {
            $this->eventsEmitter = new EventsEmitter;
        }
        return $this->eventsEmitter;
    }
    
    protected function isolateContext($flags, $fn, $args = null)
    {
        $oldContext = clone $this->context;
        
        if ($flags === null) {
            $this->initContext();
        } else {
            foreach ($flags as $k => $v) {
                if ($v === null) {
                    $this->initContext();
                } else {
                    $this->context->$k = $v;
                }
            }
        }
        
        $ret = $args ? call_user_func_array(array($this, $fn), $args) : $this->$fn();
        
        $this->context = $oldContext;
        
        return $ret;
    }
    
    protected function createNode($nodeType, $position)
    {
        $nodeClass = "\\Peast\\Syntax\\Node\\" . $nodeType;
        $node = new $nodeClass;
        
        if ($position instanceof Node\Node || $position instanceof Token) {
            $position = $position->location->start;
        } elseif (is_array($position)) {
            if (count($position)) {
                $position = $position[0]->location->start;
            } else {
                $position = $this->scanner->getPosition();
            }
        }
        $node->location->start = $position;
        
        $this->eventsEmitter && $this->eventsEmitter->fire(
            "NodeCreated", array($node)
        );
        
        return $node;
    }
    
    protected function completeNode(Node\Node $node, $position = null)
    {
        $node->location->end = $position ?: $this->scanner->getPosition();
        
        $this->eventsEmitter && $this->eventsEmitter->fire(
            "NodeCompleted", array($node)
        );
        
        return $node;
    }
    
    protected function error($message = "", $position = null)
    {
        if (!$message) {
            $token = $this->scanner->getToken();
            if ($token === null) {
                $message = "Unexpected end of input";
            } else {
                $position = $token->location->start;
                $message = "Unexpected: " . $token->value;
            }
        }
        if (!$position) {
            $position = $this->scanner->getPosition();
        }
        throw new Exception($message, $position);
    }
    
    protected function assertEndOfStatement()
    {
        if (!$this->scanner->noLineTerminators()) {
            return true;
        } else {
            if ($this->scanner->consume(";")) {
                return true;
            }
            $token = $this->scanner->getToken();
            if (!$token || $token->value === "}") {
                return true;
            }
        }
        $this->error();
    }
    
    protected function charSeparatedListOf($fn, $char = ",")
    {
        $list = array();
        $valid = true;
        while ($param = $this->$fn()) {
            $list[] = $param;
            $valid = true;
            if (!$this->scanner->consume($char)) {
                break;
            } else {
                $valid = false;
            }
        }
        if (!$valid) {
            $this->error();
        }
        return $list;
    }
}