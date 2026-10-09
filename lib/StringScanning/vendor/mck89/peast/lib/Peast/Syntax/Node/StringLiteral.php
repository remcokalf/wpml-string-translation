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

class StringLiteral extends Literal
{
    protected $propertiesMap = array(
        "format" => false
    );
    
    const DOUBLE_QUOTED = "double";
    
    const SINGLE_QUOTED = "single";
    
    protected $format = self::DOUBLE_QUOTED;
    
    public function setValue($value)
    {
        $this->value = (string) $value;
        return $this->setFormat($this->format);
    }
    
    public function setRaw($raw)
    {
        if (!is_string($raw) || strlen($raw) < 2) {
            throw new \Exception("Invalid string");
        }
        $startQuote = $raw[0];
        $endQuote = substr($raw, -1);
        if (($startQuote !== "'" && $startQuote !== '"') ||
            $startQuote !== $endQuote
        ) {
            throw new \Exception("Invalid string");
        }
        $this->value = Utils::unquoteLiteralString($raw);
        $this->setFormat($raw[0] === "'" ?
            self::SINGLE_QUOTED :
            self::DOUBLE_QUOTED
        );
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
        $quote = $format === self::SINGLE_QUOTED ? "'" : '"';
        $this->raw = Utils::quoteLiteralString($this->value, $quote);
        return $this;
    }
}