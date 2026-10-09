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

use Peast\Syntax\SourceLocation;
use Peast\Syntax\Position;

abstract class Node implements \JSONSerializable
{
    protected $propertiesMap = array(
        "type" => false,
        "location" => false,
        "leadingComments" => false,
        "trailingComments" => false
    );
    
    public $location;
    
    protected $leadingComments = array();

    protected $trailingComments = array();

    public function __construct()
    {
        $this->location = new SourceLocation;
    }
    
    public function getType()
    {
        $class = explode("\\", get_class($this));
        return array_pop($class);
    }
    
    public function setLeadingComments($comments)
    {
        $this->assertArrayOf($comments, "Comment");
        $this->leadingComments = $comments;
        return $this;
    }

    public function getLeadingComments()
    {
        return $this->leadingComments;
    }

    public function setTrailingComments($comments)
    {
        $this->assertArrayOf($comments, "Comment");
        $this->trailingComments = $comments;
        return $this;
    }

    public function getTrailingComments()
    {
        return $this->trailingComments;
    }

    public function getLocation()
    {
        return $this->location;
    }
    
    public function setStartPosition(Position $position)
    {
        $this->location->start = $position;
        return $this;
    }
    
    public function setEndPosition(Position $position)
    {
        $this->location->end = $position;
        return $this;
    }
    
    public function traverse(callable $fn, $options = array())
    {
        $traverser = new \Peast\Traverser($options);
        $traverser->addFunction($fn)->traverse($this);
        return $this;
    }
    
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        $ret = array();
        $props = \Peast\Syntax\Utils::getNodeProperties($this);
        foreach ($props as $prop) {
            $ret[$prop["name"]] = $this->{$prop["getter"]}();
        }
        return $ret;
    }
    
    public function render(\Peast\Formatter\Base $formatter)
    {
        $renderer = new \Peast\Renderer();
        return $renderer->setFormatter($formatter)->render($this);
    }
    
    protected function assertArrayOf($params, $classes, $allowNull = false)
    {
        if (!is_array($classes)) {
            $classes = array($classes);
        }
        if (!is_array($params)) {
            $this->typeError($params, $classes, $allowNull, true);
        } else {
            foreach ($params as $param) {
                foreach ($classes as $class) {
                    if ($param === null && $allowNull) {
                        continue 2;
                    } else {
                        $c = "Peast\\Syntax\\Node\\$class";
                        if ($param instanceof $c) {
                            continue 2;
                        }
                    }
                }
                $this->typeError($param, $classes, $allowNull, true, true);
            }
        }
    }
    
    protected function assertType($param, $classes, $allowNull = false)
    {
        if (!is_array($classes)) {
            $classes = array($classes);
        }
        if ($param === null) {
            if (!$allowNull) {
                $this->typeError($param, $classes, $allowNull);
            }
        } else {
            foreach ($classes as $class) {
                $c = "Peast\\Syntax\\Node\\$class";
                if ($param instanceof $c) {
                    return;
                }
            }
            $this->typeError($param, $classes, $allowNull);
        }
    }
    
    protected function typeError(
        $param, $allowedTypes, $allowNull = false, $array = false,
        $inArray = false
    ) {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        $method = $backtrace[2]["class"] . "::" . $backtrace[2]["function"];
        $msg = "Argument 0 passed to $method must be ";
        if ($array) {
            $msg .= "an array of ";
        }
        $msg .= implode(" or ", $allowedTypes);
        if ($allowNull) {
            $msg .= " or null";
        }
        if (is_object($param)) {
            $parts = explode("\\", get_class($param));
            $type = array_pop($parts);
        } else {
            $type = gettype($param);
        }
        if ($inArray) {
            $type = "array of $type";
        }
        $msg .= ", $type given";
        if (version_compare(phpversion(), '7', '>=')) {
            throw new \TypeError($msg);
        } else {
            trigger_error($msg, E_USER_ERROR);
        }
    }
}