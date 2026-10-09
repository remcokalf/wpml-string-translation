<?php
/**
 * This file is part of the Peast package
 *
 * (c) Marco Marchiò <marco.mm89@gmail.com>
 *
 * For the full copyright and license information refer to the LICENSE file
 * distributed with this source code
 */
namespace Peast\Syntax\JSX;

use Peast\Syntax\Token;

trait Scanner
{
    public function reconsumeCurrentTokenAsJSXText()
    {
        foreach (array($this->currentToken, $this->nextToken) as $token) {
            if ($token && isset($this->brackets[$token->value])) {
                if ($refBracket = $this->brackets[$token->value]) {
                    $this->openBrackets[$refBracket]++;
                } else {
                    $this->openBrackets[$token->value]--;
                }
            }
        }
        $this->nextToken = null;
        $this->currentToken = null;

        $startPosition = $this->getPosition();
        $this->setScanPosition($startPosition);
        $result = $this->consumeUntil(array("{", "<"), false, false);
        if ($result) {
            $this->currentToken = new Token(Token::TYPE_JSX_TEXT, $result[0]);
            $this->currentToken->location->start = $startPosition;
            $this->currentToken->location->end = $this->getPosition(true);
        }
        return $this->currentToken;
    }
    
    
    public function reconsumeCurrentTokenInJSXMode()
    {
        $this->jsx = true;
        $this->nextToken = null;
        $this->currentToken = null;
        $startPosition = $this->getPosition();
        $this->setScanPosition($startPosition);
        $token = $this->getToken();
        $this->jsx = false;
        return $token;
    }
    
    public function scanJSXString()
    {
        return $this->scanString(false);
    }
    
    public function scanJSXPunctuator()
    {
        $char = $this->charAt();
        if ($char === ">") {
            $this->index++;
            $this->column++;
            return new Token(Token::TYPE_PUNCTUATOR, $char);
        }
        return $this->scanPunctuator();
    }
    
    public function scanJSXIdentifier()
    {
        $buffer = "";
        $char = $this->charAt();
        if ($char !== null && $this->isIdentifierChar($char)) {
            
            do {
                $buffer .= $char;
                $this->index++;
                $this->column++;
                $char = $this->charAt();
            } while (
                $char !== null &&
                ($this->isIdentifierChar($char, false) || $char === "-")
            );
        }
        
        return $buffer === "" ? null : new Token(Token::TYPE_JSX_IDENTIFIER, $buffer);
    }
}
