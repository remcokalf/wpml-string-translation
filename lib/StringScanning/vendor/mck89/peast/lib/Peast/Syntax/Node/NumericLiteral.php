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

class NumericLiteral extends Literal
{
    protected $propertiesMap = array(
        "format" => false
    );
    
    const DECIMAL = "decimal";
    
    const HEXADECIMAL = "hexadecimal";
    
    const OCTAL = "octal";
    
    const BINARY = "binary";
    
    protected $format = self::DECIMAL;
    
    protected $forms = array(
        "b" => array(
            "check" => "/^0b[01]+[01_]*$/i",
            "conv" => "bindec",
            "format" => self::BINARY
        ),
        "o" => array(
            "check" => "/^0o[0-7]+[0-7_]*$/i",
            "conv" => "octdec",
            "format" => self::OCTAL
        ),
        "x" => array(
            "check" => "/^0x[0-9a-f]+[0-9a-f_]*$/i",
            "conv" => "hexdec",
            "format" => self::HEXADECIMAL
        ),
    );
    
    public function setValue($value)
    {
        $value = (float) $value;
        $intValue = (int) $value;
        if ($value == $intValue) {
            $value = $intValue;
        }
        $this->value = $value;
        return $this->setFormat($this->format);
    }
    
    public function setRaw($raw)
    {
        $value = $raw;
        $format = self::DECIMAL;
        if (is_string($value) && $value !== "") {
            $startZero = $value[0] === "0";
            $form = $startZero && isset($value[1]) ? strtolower($value[1]) : null;
            if (preg_match("/^_|_$/", $value)) {
                throw new \Exception("Invalid numeric value");
            } elseif (isset($this->forms[$form !== null ? $form : ''])) {
                $formDef = $this->forms[$form];
                if (!preg_match($formDef["check"], $value)) {
                    throw new \Exception("Invalid " . $formDef["format"]);
                }
                $value = str_replace("_", "", $value);
                $value = $formDef["conv"]($value);
                $format = $formDef["format"];
            } elseif ($startZero && preg_match("/^0[0-7_]+$/", $value)) {
                $value = str_replace("_", "", $value);
                $value = octdec($value);
                $format = self::OCTAL;
            } elseif (
                preg_match("/^([\d_]*\.?[\d_]*)(?:e[+\-]?[\d_]+)?$/i", $value, $match) &&
                $match[1] !== "" &&
                $match[1] !== "." &&
                !preg_match("/_e|e[+-]?_|_$/", $value)
            ) {
                $value = str_replace("_", "", $value);
            } else {
                throw new \Exception("Invalid numeric value");
            }
        } elseif (!is_int($value) && !is_float($value)) {
            throw new \Exception("Invalid numeric value");
        }
        $value = (float) $value;
        $checkInt = true;
        if (defined("PHP_INT_MAX") && defined("PHP_INT_MIN")) {
            $checkInt = $value <= PHP_INT_MAX && $value >= PHP_INT_MIN;
        }
        if ($checkInt) {
            $intValue = (int) $value;
            if ($value == $intValue) {
                $value = $intValue;
            }
        }
        $this->format = $format;
        $this->value = $value;
        $this->raw = $raw;
        return $this;
    }
    
    public function getFormat()
    {
        return $this->format;
    }
    
    public function setFormat($format)
    {
        $this->format = $format;
        switch ($format) {
            case self::BINARY:
                $this->raw = "0b" . decbin($this->value);
            break;
            case self::OCTAL:
                $this->raw = "0o" . decoct($this->value);
            break;
            case self::HEXADECIMAL:
                $this->raw = "0x" . dechex($this->value);
            break;
            default:
                $this->raw = (string) $this->value;
            break;
        }
        return $this;
    }
}