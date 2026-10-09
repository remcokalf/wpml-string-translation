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

use Peast\Syntax\Utils;

class Identifier extends Node implements Expression, Pattern
{
    protected $propertiesMap = array(
        "name" => false,
        "rawName" => false,
    );
    
    protected $name;
    
    protected $rawName;
    
    public function getName()
    {
        return $this->name;
    }
    
    public function setName($name)
    {
        $this->name = $this->rawName = $name;
        return $this;
    }
    
    public function getRawName()
    {
        return $this->rawName;
    }
    
    public function setRawName($name)
    {
        $this->rawName = $name;
        if (strpos($name, "\\") !== false) {
            $this->name = preg_replace_callback(
                "#\\\\u(?:\{([a-fA-F0-9]+)\}|([a-fA-F0-9]{4}))#",
                function ($match) {
                    return Utils::unicodeToUtf8(hexdec($match[1] ? : $match[2]));
                },
                $name
            );
        } else {
            $this->name = $name;
        }
        return $this;
    }
}