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

class Comment extends Node
{
    const KIND_INLINE = "inline";
    
    const KIND_MULTILINE = "multiline";
    
    const KIND_HTML_OPEN = "html-open";
    
    const KIND_HTML_CLOSE = "html-close";
    
    const KIND_HASHBANG = "hashbang";
    
    protected $propertiesMap = array(
        "kind" => false,
        "text" => false
    );
    
    protected $kind;
    
    protected $text;
    
    public function getKind()
    {
        return $this->kind;
    }
    
    public function setKind($kind)
    {
        $this->kind = $kind;
        return $this;
    }
    
    public function getText()
    {
        return $this->text;
    }
    
    public function setText($text)
    {
        $this->text = $text;
        return $this;
    }
    
    public function getRawText()
    {
        $text = $this->getText();
        $kind = $this->getKind();

        if ($kind === self::KIND_MULTILINE) {
            $sanitize = "*/";
        } else {
            $sanitize = array("\n", "\r");
        }
        $text = str_replace($sanitize, "", $text);
        
        if ($kind === self::KIND_INLINE) {
            return "//" . $text;
        } elseif ($kind === self::KIND_HASHBANG) {
            return "#!" . $text;
        } elseif ($kind === self::KIND_HTML_OPEN) {
            return "<!--" . $text;
        } elseif ($kind === self::KIND_HTML_CLOSE) {
            return "-->" . $text;
        } else {
            return "/*" . $text . "*/";
        }
    }
    
    public function setRawText($rawText)
    {
        $start = substr($rawText, 0, 2);
        if ($start === "//") {
            $kind = self::KIND_INLINE;
            $text = substr($rawText, 2);
        } elseif ($start === "/*" && substr($rawText, -2) === "*/") {
            $kind = self::KIND_MULTILINE;
            $text = substr($rawText, 2, -2);
        } elseif ($start === "#!") {
            $kind = self::KIND_HASHBANG;
            $text = substr($rawText, 2);
        } elseif ($start === "<!" && substr($rawText, 2, 2) === "--") {
            $kind = self::KIND_HTML_OPEN;
            $text = substr($rawText, 4);
        } elseif ($start === "--" && substr($rawText, 2, 1) === ">") {
            $kind = self::KIND_HTML_CLOSE;
            $text = substr($rawText, 3);
        } else {
            throw new \Exception("Invalid comment");
        }
        return $this->setKind($kind)->setText($text);
    }
    
    public function setLeadingComments($comments)
    {
        return $this;
    }
    
    public function setTrailingComments($comments)
    {
        return $this;
    }
    
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        $ret = parent::jsonSerialize();
        unset($ret["leadingComments"]);
        unset($ret["trailingComments"]);
        return $ret;
    }
}