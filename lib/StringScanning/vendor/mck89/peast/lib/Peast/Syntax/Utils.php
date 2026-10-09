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

class Utils
{
    static public function stringToUTF8Array($str, $strictEncoding = true)
    {
        if ($str === "") {
            return array();
        }
        $ret = preg_split('//u', $str, -1, PREG_SPLIT_NO_EMPTY);
        if (preg_last_error() === PREG_BAD_UTF8_ERROR) {
            if (!$strictEncoding) {
                $str = mb_convert_encoding($str, 'UTF-8', 'UTF-8');
                $ret = self::stringToUTF8Array($str, false);
            } else {
                throw new EncodingException("String contains invalid UTF-8");
            }
        }
        return $ret;
    }
    
    static public function unicodeToUtf8($num)
    {
        if ($num <= 0x7F) {
            return chr($num);
        } elseif ($num <= 0x7FF) {
            return chr(($num >> 6) + 192) .
                   chr(($num & 63) + 128);
        } elseif ($num <= 0xFFFF) {
            return chr(($num >> 12) + 224) .
                   chr((($num >> 6) & 63) + 128) .
                   chr(($num & 63) + 128);
        } elseif ($num <= 0x1FFFFF) {
            return chr(($num >> 18) + 240) .
                   chr((($num >> 12) & 63) + 128) .
                   chr((($num >> 6) & 63) + 128) .
                   chr(($num & 63) + 128);
        }
        return '';
    }
    
    protected static $lineTerminatorsCache;
    
    protected static function getLineTerminators()
    {
        if (!self::$lineTerminatorsCache) {
            self::$lineTerminatorsCache = array();
            foreach (Scanner::$lineTerminatorsChars as $char) {
                self::$lineTerminatorsCache[] = is_int($char) ?
                                                self::unicodeToUtf8($char) :
                                                $char;
            }
        }
        return self::$lineTerminatorsCache;
    }

    static public function surrogatePairToUtf8($first, $second)
    {
        $value = ((hexdec($first) & 0x3ff) << 10) | (hexdec($second) & 0x3ff);
        return self::unicodeToUtf8($value + 0x10000);
    }
    
    static public function unquoteLiteralString($str)
    {
        $str = substr($str, 1, -1);
        
        if (strpos($str, "\\") === false) {
            return $str;
        }
        
        $lineTerminators = self::getLineTerminators();
        
        $surrogatePairsReg = sprintf(
            'u(?:%1$s|\{%1$s\})\\\\u(?:%2$s|\{%2$s\})',
            "[dD][89abAB][0-9a-fA-F]{2}", "[dD][c-fC-F][0-9a-fA-F]{2}"
        );
        
        $patterns = array(
            $surrogatePairsReg,
            "u\{[a-fA-F0-9]+\}",
            "u[a-fA-F0-9]{4}",
            "x[a-fA-F0-9]{2}",
            "0[0-7]{2}",
            "[1-7][0-7]",
            "."
        );
        $reg = "/\\\\(" . implode("|", $patterns) . ")/s";
        $simpleSequence = array(
            "n" => "\n",
            "f" => "\f",
            "r" => "\r",
            "t" => "\t",
            "v" => "\v",
            "b" => "\x8"
        );
        $replacement = function ($m) use ($simpleSequence, $lineTerminators) {
            $type = $m[1][0];
            if (isset($simpleSequence[$type])) {
                return $simpleSequence[$type];
            } elseif ($type === "u" || $type === "x") {
                if (strlen($m[1]) === 1) {
                    return "\\$type";
                }
                if ($type === "u" && strpos($m[1], "\\") !== false) {
                    $points = explode("\\", $m[1]);
                    return Utils::surrogatePairToUtf8(
                        str_replace(array("{", "}", "u"), "", $points[0]),
                        str_replace(array("{", "}", "u"), "", $points[1])
                    );
                }
                $code = substr($m[1], 1);
                $code = str_replace(array("{", "}"), "", $code);
                return Utils::unicodeToUtf8(hexdec($code));
            } elseif ($type >= "0" && $type <= "7") {
                if (strlen($m[1]) === 1) {
                    return "\\$type";
                }
                return Utils::unicodeToUtf8(octdec($m[1]));
            } elseif (in_array($m[1], $lineTerminators)) {
                return "";
            } else {
                return $m[1];
            }
        };
        return preg_replace_callback($reg, $replacement, $str);
    }
    
    static public function quoteLiteralString($str, $quote)
    {
        $escape = self::getLineTerminators();
        $escape[] = $quote;
        $escape[] = "\\\\";
        $reg = "/(" . implode("|", $escape) . ")/";
        $str = preg_replace($reg, "\\\\$1", $str);
        return $quote . $str . $quote;
    }
    
    static protected function getPropertiesMap($node)
    {
        static $cache = array();
        
        if ($node instanceof \ReflectionClass) {
            $className = $node->getName();
        } else {
            $className = get_class($node);
        }
        
        if (!isset($cache[$className])) {
            $class = new \ReflectionClass($className);
            $parent = $class->getParentClass();
            $props = $parent ? self::getPropertiesMap($parent) : array();
            $defaults = $class->getDefaultProperties();
            if (isset($defaults["propertiesMap"])) {
                $props = array_merge($props, $defaults["propertiesMap"]);
            }
            $cache[$className] = $props;
        }
        return $cache[$className];
    }
    
    static public function getNodeProperties(Node\Node $node, $traversable = false)
    {
        $props = self::getPropertiesMap($node);
        return array_map(
            function ($prop) {
                $ucProp = ucfirst($prop);
                return array(
                    "name" => $prop,
                    "getter" => "get$ucProp",
                    "setter" => "set$ucProp"
                );
            },
            array_keys($traversable ? array_filter($props) : $props)
        );
    }

    static public function getExpandedNodeProperties(Node\Node $node)
    {
        $ret = array();
        $props = self::getNodeProperties($node, true);
        foreach ($props as $prop) {
            $val = $node->{$prop["getter"]}();
            if (is_array($val)) {
                $ret = array_merge($ret, $val);
            } else {
                $ret[] = $val;
            }
        }
        return $ret;
    }

    static public function removeArrayValue(&$array, $val)
    {
        array_splice($array, array_search($val, $array), 1);
    }
}