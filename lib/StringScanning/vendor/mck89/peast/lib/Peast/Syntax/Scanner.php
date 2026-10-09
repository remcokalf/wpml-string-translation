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

class Scanner
{
    use JSX\Scanner;

    protected $features;

    protected $column = 0;
    
    protected $line = 1;
    
    protected $index = 0;
    
    protected $length;
    
    protected $source;
    
    protected $position;
    
    protected $currentToken;
    
    protected $nextToken;
    
    protected $strictMode = false;
    
    protected $registerTokens = false;
    
    protected $isModule = false;
    
    protected $comments = false;
    
    protected $jsx = false;
    
    protected $tokens = array();
    
    protected $commentsMap = array();
    
    protected $eventsEmitter;
    
    protected $idStartRegex = "/[\p{Lu}\p{Ll}\p{Lt}\p{Lm}\p{Lo}\p{Nl}\x{1885}\x{1886}\x{2118}\x{212E}\x{309B}\x{309C}]/u";
    
    protected $idPartRegex = "/[\p{Lu}\p{Ll}\p{Lt}\p{Lm}\p{Lo}\p{Nl}\x{1885}\x{1886}\x{2118}\x{212E}\x{309B}\x{309C}\p{Mn}\p{Mc}\p{Nd}\p{Pc}\x{00B7}\x{0387}\x{1369}\x{136A}\x{136B}\x{136C}\x{136D}\x{136E}\x{136F}\x{1370}\x{1371}\x{19DA}\x{200C}\x{200D}]/u";
    
    protected $keywords = array(
        "break", "do", "in", "typeof", "case", "else", "instanceof", "var",
        "catch", "export", "new", "void", "class", "extends", "return", "while",
        "const", "finally", "super", "with", "continue", "for", "switch",
        "debugger", "function", "this", "default", "if", "throw",
        "delete", "import", "try", "enum", "await"
    );
    
    protected $strictModeKeywords = array(
        "implements", "interface", "package", "private", "protected", "public",
        "static", "let", "yield"
    );

    protected $punctuators = array(
        ".", ";", ",", "<", ">", "<=", ">=", "==", "!=", "===", "!==", "+",
        "-", "*", "%", "++", "--", "<<", ">>", ">>>", "&", "|", "^", "!", "~",
        "&&", "||", "?", ":", "=", "+=", "-=", "*=", "%=", "<<=", ">>=", ">>>=",
        "&=", "|=", "^=", "=>", "...", "/", "/=", "**", "**=", "??", "?.",
        "&&=", "||=", "??="
    );
    
    protected $punctuatorsLSM;
    
    protected $stringsStopsLSM;
    
    protected $brackets = array(
        "(" => "", "[" => "", "{" => "", ")" => "(", "]" => "[", "}" => "{"
    );
    
    protected $openBrackets = array();
    
    protected $openTemplates = array();
    
    protected $whitespaces = array(
        " ", "\t", "\n", "\r", "\f", "\v", 0x00A0, 0xFEFF, 0x00A0,
        0x1680, 0x2000, 0x2001, 0x2002, 0x2003, 0x2004, 0x2005, 0x2006,
        0x2007, 0x2008, 0x2009, 0x200A, 0x202F, 0x205F, 0x3000, 0x2028,
        0x2029
    );
    
    public static $lineTerminatorsChars = array("\n", "\r", 0x2028, 0x2029);
    
    public static $lineTerminatorsSequences = array("\r\n");
    
    protected $linesSplitter;
    
    protected $lineTerminators;
    
    protected $stateProps = array("position", "index", "column", "line",
                                  "currentToken", "nextToken", "strictMode",
                                  "openBrackets", "openTemplates",
                                  "commentsMap");
    
    protected $numbers = array("0", "1", "2", "3", "4", "5", "6", "7", "8",
                               "9");
    
    protected $xnumbers = array("0", "1", "2", "3", "4", "5", "6", "7", "8",
                                "9", "a", "b", "c", "d", "e", "f", "A", "B",
                                "C", "D", "E", "F");

    protected $onumbers = array("0", "1", "2", "3", "4", "5", "6", "7");

    protected $bnumbers = array("0", "1");

    function __construct(
        $source, Features $features, $options
    ) {
        $this->features = $features;

        $encoding = isset($options["sourceEncoding"]) ?
                    $options["sourceEncoding"] :
                    null;
        
        $this->stripBOM($source, $encoding);
        
        if ($encoding && !preg_match("/UTF-?8/i", $encoding)) {
            $source = mb_convert_encoding($source, "UTF-8", $encoding);
        }
        
        $this->source = Utils::stringToUTF8Array(
            $source,
            !isset($options["strictEncoding"]) || $options["strictEncoding"]
        );
        $this->length = count($this->source);
        
        $this->lineTerminators = array_merge(
            self::$lineTerminatorsSequences, self::$lineTerminatorsChars
        );
        foreach (array("whitespaces", "lineTerminators") as $key) {
            foreach ($this->$key as $i => $char) {
                if (is_int($char)) {
                    $this->{$key}[$i] = Utils::unicodeToUtf8($char);
                }
            }
        }

        if (!$this->features->exponentiationOperator) {
            Utils::removeArrayValue($this->punctuators, "**");
            Utils::removeArrayValue($this->punctuators, "**=");
        }

        if (!$this->features->optionalChaining) {
            Utils::removeArrayValue($this->punctuators, "?.");
        }

        if (!$this->features->logicalAssignmentOperators) {
            Utils::removeArrayValue($this->punctuators, "&&=");
            Utils::removeArrayValue($this->punctuators, "||=");
            Utils::removeArrayValue($this->punctuators, "??=");
        }
        
        $this->punctuatorsLSM = new LSM($this->punctuators);
        
        $this->stringsStopsLSM = new LSM($this->lineTerminators, true);
        
        if ($this->features->paragraphLineSepInStrings) {
            $this->stringsStopsLSM->remove(Utils::unicodeToUtf8(0x2028));
            $this->stringsStopsLSM->remove(Utils::unicodeToUtf8(0x2029));
        }

        if ($this->features->asyncAwait) {
            Utils::removeArrayValue($this->keywords, "await");
        }
        
        $this->linesSplitter = "/" .
                               implode("|", $this->lineTerminators) .
                               "/uS";
        $this->position = new Position(0, 0, 0);
    }
    
    public function stripBOM(&$source, &$encoding)
    {
        $boms = array(
            "\xEF" => array(array("\xBB", "\xBF"), "UTF-8"),
            "\xFE" => array(array("\xFF"), "UTF-16BE"),
            "\xFF" => array(array("\xFE"), "UTF-16LE"),
        );
        if (!isset($source[0]) || !isset($boms[$source[0]])) {
            return;
        }
        $bom = $boms[$source[0]];
        $l = count($bom[0]);
        for ($i = 0; $i < $l; $i++) {
            if (!isset($source[$i + 1]) || $source[$i + 1] !== $bom[0][$i]) {
                return;
            }
        }
        $source = substr($source, $l + 1);
        if (!$encoding) {
            $encoding = $bom[1];
        }
    }
    
    public function enableModuleMode($enable = true)
    {
        $this->isModule = $enable;
        return $this;
    }
    
    public function enableComments($enable = true)
    {
        $this->comments = $enable;
        return $this;
    }
    
    public function enableTokenRegistration($enable = true)
    {
        $this->registerTokens = $enable;
        return $this;
    }
    
    public function getTokens()
    {
        return $this->tokens;
    }
    
    public function getEventsEmitter()
    {
        if (!$this->eventsEmitter) {
            $this->eventsEmitter = new EventsEmitter;
        }
        return $this->eventsEmitter;
    }
    
    public function setStrictMode($strictMode)
    {
        $this->strictMode = $strictMode;
        return $this;
    }
    
    public function getStrictMode()
    {
        return $this->strictMode;
    }
    
    public function isStrictModeKeyword($token)
    {
        return $token->type === Token::TYPE_KEYWORD &&
               (in_array($token->value, $this->keywords) || (
                $this->strictMode &&
                in_array($token->value, $this->strictModeKeywords)));
    }
    
    public function getState()
    {
        $token = $this->currentToken ?: $this->getToken();
        if ($token && $token->value !== "/") {
            $this->getNextToken();
        }
        $state = array();
        foreach ($this->stateProps as $prop) {
            $state[$prop] = $this->$prop;
        }
        if ($this->registerTokens) {
            $state["tokensNum"] = count($this->tokens);
        }
        $this->eventsEmitter && $this->eventsEmitter->fire(
            "FreezeState", array(&$state)
        );
        return $state;
    }
    
    public function setState($state)
    {
        if ($this->registerTokens) {
            if (isset($this->tokens[$state["tokensNum"]])) {
                for ($i = count($this->tokens) - 1; $i >= $state["tokensNum"]; $i--) {
                    array_pop($this->tokens);
                }
            }
            unset($state["tokensNum"]);
        }
        $this->eventsEmitter && $this->eventsEmitter->fire(
            "ResetState", array(&$state)
        );
        foreach ($state as $key => $value) {
            $this->$key = $value;
        }
        return $this;
    }
    
    public function getPosition($scanPosition = false)
    {
        if ($scanPosition) {
            return new Position($this->line, $this->column, $this->index);
        } else {
            return $this->position;
        }
    }
    
    public function setScanPosition(Position $position)
    {
        $this->line = $position->getLine();
        $this->column = $position->getColumn();
        $this->index = $position->getIndex();
        return $this;
    }
    
    public function charAt($index = null)
    {
        if ($index === null) {
            $index = $this->index;
        }
        return $index < $this->length ? $this->source[$index] : null;
    }
    
    protected function error($message = null)
    {
        if (!$message) {
            $message = "Unexpected " . $this->charAt();
        }
        throw new Exception($message, $this->getPosition(true));
    }
    
    public function consumeToken()
    {
        $this->position = $this->currentToken->location->end;
        
        if ($this->comments) {
            $this->consumeCommentsForCurrentToken();
        }
        
        if ($this->registerTokens) {
            $this->tokens[] = $this->currentToken;
        }
        
        $this->eventsEmitter && $this->eventsEmitter->fire(
            "TokenConsumed", array($this->currentToken)
        );
        
        $this->currentToken = $this->nextToken;
        $this->nextToken = null;
        return $this;
    }
    
    public function consume($expected)
    {
        $token = $this->currentToken ?: $this->getToken();
        if ($token && $token->value === $expected) {
            $this->consumeToken();
            return $token;
        }
        return null;
    }
    
    public function consumeOneOf($expected)
    {
        $token = $this->currentToken ?: $this->getToken();
        if ($token && in_array($token->value, $expected)) {
            $this->consumeToken();
            return $token;
        }
        return null;
    }
    
    public function noLineTerminators($nextToken = false)
    {
        if ($nextToken) {
            $nextToken = $this->getNextToken();
            $refLine = !$nextToken ? null :
                        $nextToken->location->end->getLine();
        } else {
            $refLine = $this->getPosition()->getLine();
        }
        $token = $this->currentToken ?: $this->getToken();
        return $token &&
               $token->location->start->getLine() === $refLine;
    }
    
    public function isBefore($expected, $nextToken = false)
    {
        $token = $this->currentToken ?: $this->getToken();
        if (!$token) {
            return false;
        } elseif (in_array($token->value, $expected)) {
            return true;
        } elseif (!$nextToken) {
            return false;
        }
        if (!$this->getNextToken()) {
            return false;
        }
        foreach ($expected as $val) {
            if (!is_array($val) || $val[0] !== $token->value) {
                continue;
            }
            if (($val[1] === true && $this->noLineTerminators(true)) ||
                ($val[1] !== true && $val[1] === $this->nextToken->value)) {
                return true;
            }
        }
        return false;
    }
    
    public function getNextToken()
    {
        if (!$this->nextToken) {
            $token = $this->currentToken ?: $this->getToken();
            $this->currentToken = null;
            $this->nextToken = $this->getToken(true);
            $this->currentToken = $token;
        }
        return $this->nextToken;
    }
    
    public function getToken($skipEOFChecks = false)
    {
        if ($this->currentToken) {
            return $this->currentToken;
        }
        
        $comments = $this->skipWhitespacesAndComments();
        
        if ($comments) {
            foreach ($comments as $comment) {
                $this->eventsEmitter && $this->eventsEmitter->fire(
                    "TokenCreated", array($comment)
                );
            }
        }

        if ($this->index >= $this->length) {
            if (!$skipEOFChecks) {
                foreach ($this->openBrackets as $bracket => $num) {
                    if ($num) {
                        $this->error("Unclosed $bracket");
                    }
                }

                if (count($this->openTemplates)) {
                    $this->error("Unterminated template");
                }
            }
            
            if ($this->comments && $comments) {
                $this->commentsForCurrentToken($comments);
            }
            
            $this->eventsEmitter && $this->eventsEmitter->fire(
                "EndReached"
            );
            
            return null;
        }
        
        $startPosition = $this->getPosition(true);
        $origException = null;
        try {
            
            if (
                ($this->jsx && ($token = $this->scanJSXIdentifier())) ||
                ($token = $this->scanTemplate()) ||
                ($token = $this->scanNumber()) ||
                ($this->jsx && ($token = $this->scanJSXPunctuator())) ||
                ($token = $this->scanPunctuator()) ||
                ($token = $this->scanKeywordOrIdentifier()) ||
                ($this->jsx && ($token = $this->scanJSXString())) ||
                ($token = $this->scanString())
            ) {
                $token->location->start = $startPosition;
                $token->location->end = $this->getPosition(true);
                $this->currentToken = $token;
                                            
                if ($this->comments && $comments) {
                    $this->commentsForCurrentToken($comments);
                }
                
                $this->eventsEmitter && $this->eventsEmitter->fire(
                    "TokenCreated", array($this->currentToken)
                );
                
                return $this->currentToken;
            }
            
        } catch (Exception $e) {
            $origException = $e;
        }
        
        if ($this->isAfterSlash($startPosition)) {
            $this->setScanPosition($startPosition);
            return null;
        }
        
        if ($origException) {
            throw $origException;
        }
        $this->error();
    }
    
    public function consumeEnd()
    {
        if ($this->comments) {
            $this->consumeCommentsForCurrentToken();
        }
        
        $this->eventsEmitter && $this->eventsEmitter->fire(
            "EndReached"
        );
        
        return $this;
    }
    
    protected function commentsForCurrentToken($comments = null)
    {
        $id = $this->currentToken ? spl_object_hash($this->currentToken) : "";
        if ($comments !== null) {
            $this->commentsMap[$id] = $comments;
        } elseif (isset($this->commentsMap[$id])) {
            $comments = $this->commentsMap[$id];
            unset($this->commentsMap[$id]);
        }
        return $comments;
    }
    
    protected function consumeCommentsForCurrentToken()
    {
        $comments = $this->commentsForCurrentToken();
        if ($comments && ($this->registerTokens || $this->eventsEmitter)) {
            foreach ($comments as $comment) {
                if ($this->registerTokens) {
                    $this->tokens[] = $comment;
                }
                $this->eventsEmitter && $this->eventsEmitter->fire(
                    "TokenConsumed", array($comment)
                );
            }
        }
        return $this;
    }
    
    protected function isAfterSlash($position)
    {
        $idx = $position->getIndex() - 1;
        while ($idx >= 0) {
            $char = $this->charAt($idx);
            if ($char === "/") {
                return $idx === 0 || $this->charAt($idx - 1) !== "*";
            }
            elseif (in_array($char, $this->whitespaces) && !in_array($char, $this->lineTerminators)) {
                $idx--;
            }
            elseif ($char === "=" && $this->charAt($idx - 1) === "/") {
                return true;
            }
            else {
                break;
            }
        }
        return false;
    }
    
    public function reconsumeCurrentTokenAsRegexp()
    {
        $token = $this->currentToken ?: $this->getToken();
        $value = $token ? $token->value : null;
        
        if (!$value || $value[0] !== "/") {
            return null;
        }
        
        $startPosition = $token->location->start;
        $this->setScanPosition($startPosition);
        
        $buffer = "/";
        $this->index++;
        $this->column++;
        $inClass = false;
        while (true) {
            $stops = $inClass ? array("]") : array("/", "[");
            $tempBuffer = $this->consumeUntil($stops);
            if ($tempBuffer === null) {
                if ($inClass) {
                    $this->error(
                        "Unterminated character class in regexp"
                    );
                } else {
                    $this->error("Unterminated regexp");
                }
            }
            $buffer .= $tempBuffer[0];
            if ($tempBuffer[1] === "/") {
                break;
            } else {
                $inClass = $tempBuffer[1] === "[";
            }
        }
        
        while (($char = $this->charAt()) !== null) {
            $lower = strtolower($char);
            if ($lower >= "a" && $lower <= "z") {
                $buffer .= $char;
                $this->index++;
                $this->column++;
            } else {
                break;
            }
        }
        
        if ($this->nextToken) {
            $nextVal = $this->nextToken->value;
            if (isset($this->brackets[$nextVal]) &&
                isset($this->openBrackets[$nextVal])
            ) {
                if ($this->brackets[$nextVal]) {
                    $this->openBrackets[$nextVal]++;
                } else {
                    $this->openBrackets[$nextVal]--;
                }
            }
            $this->nextToken = null;
        }
        
        $comments = $this->comments ? $this->commentsForCurrentToken() : null;
            
        $token = new Token(Token::TYPE_REGULAR_EXPRESSION, $buffer);
        $token->location->start = $startPosition;
        $token->location->end = $this->getPosition(true);
        $this->currentToken = $token;
                                    
        if ($comments) {
            $this->commentsForCurrentToken($comments);
        }
        
        return $this->currentToken;
    }
    
    protected function skipWhitespacesAndComments()
    {
        $comments = [];
        $content = "";
        $secStartIdx = $this->index;
        while (($char = $this->charAt()) !== null) {
            if (in_array($char, $this->whitespaces)) {
                
                $content .= $char;
                $this->index++;
                
            } elseif ($char === "/" || $char === "#") {

                $nextChar = $this->charAt($this->index + 1);

                if ($char === "#") {
                    $valid = $nextChar === "!" && $this->features->hashbangComments && !$this->index;
                } else {
                    $valid = $nextChar === "/" || $nextChar === "*";
                }

                if ($valid) {
                    
                    if ($this->comments) {
                        if ($content !== "") {
                            $this->adjustColumnAndLine($content);
                            $content = "";
                        }
                        $start = $this->getPosition(true);
                    }
                    
                    $inline = $nextChar !== "*";
                    $this->index += 2;
                    $content .= $char . $nextChar;
                    
                    while (true) {
                        $char = $this->charAt();
                        
                        if ($char === null) {
                            if (!$inline) {
                                $this->error("Unterminated comment");
                            }
                            $isEnd = true;
                        } else {
                            $content .= $char;
                            $this->index++;
                            $isEnd = $inline ?
                                     in_array($char, $this->lineTerminators) :
                                     $char === "*" && $this->charAt() === "/";
                        }
                        
                        if ($isEnd) {
                            if (!$inline) {
                                $content .= "/";
                                $this->index++;
                            }
                            if ($this->comments) {
                                if ($inline && $char !== null) {
                                    $this->index--;
                                    $content = substr($content, 0, -strlen($char));
                                }
                                $this->adjustColumnAndLine($content);
                                $token = new Token(Token::TYPE_COMMENT, $content);
                                $token->location->start = $start;
                                $token->location->end = $this->getPosition(true);
                                $comments[] = $token;
                                $content = "";
                                if ($inline && $char !== null) {
                                    $content = $char;
                                    $this->index++;
                                }
                            }
                            break;
                        }
                    }
                    
                } else {
                    break;
                }
                
            } elseif (!$this->isModule && $char === "<" &&
                $this->charAt($this->index + 1) === "!" &&
                $this->charAt($this->index + 2) === "-" &&
                $this->charAt($this->index + 3) === "-"
            ) {
                
                if ($this->comments) {
                    if ($content !== "") {
                        $this->adjustColumnAndLine($content);
                        $content = "";
                    }
                    $start = $this->getPosition(true);
                }
                
                $this->index += 4;
                $content .= "<!--";
                while (true) {
                    $char = $this->charAt();
                    if ($char === null) {
                        $isEnd = true;
                    } else {
                        $content .= $char;
                        $this->index++;
                        $isEnd = in_array($char, $this->lineTerminators);
                    }
                    if ($isEnd) {
                        if ($this->comments) {
                            if ($char !== null) {
                                $this->index--;
                                $content = substr($content, 0, -strlen($char));
                            }
                            $this->adjustColumnAndLine($content);
                            $token = new Token(Token::TYPE_COMMENT, $content);
                            $token->location->start = $start;
                            $token->location->end = $this->getPosition(true);
                            $comments[] = $token;
                            $content = "";
                            if ($char !== null) {
                                $content = $char;
                                $this->index++;
                            }
                        }
                        break;
                    }
                }
                
            } elseif (!$this->isModule && $char === "-" &&
                $this->charAt($this->index + 1) === "-" &&
                $this->charAt($this->index + 2) === ">"
            ) {
                
                $allow = false;
                if (!$secStartIdx) {
                    $allow = true;
                } else {
                    for ($index = $this->index - 1; $index >= $secStartIdx; $index--) {
                        if (in_array($this->charAt($index), $this->lineTerminators)) {
                            $allow = true;
                            break;
                        }
                    }
                }
                if ($allow) {
                    
                    if ($this->comments) {
                        if ($content !== "") {
                            $this->adjustColumnAndLine($content);
                            $content = "";
                        }
                        $start = $this->getPosition(true);
                    }
                    
                    $this->index += 3;
                    $content .= "-->";
                    while (true) {
                        $char = $this->charAt();
                        
                        if ($char === null) {
                            $isEnd = true;
                        } else {
                            $content .= $char;
                            $this->index++;
                            $isEnd = in_array($char, $this->lineTerminators);
                        }
                        
                        if ($isEnd) {
                            if ($this->comments) {
                                if ($char !== null) {
                                    $this->index--;
                                    $content = substr($content, 0, -strlen($char));
                                }
                                $this->adjustColumnAndLine($content);
                                $token = new Token(Token::TYPE_COMMENT, $content);
                                $token->location->start = $start;
                                $token->location->end = $this->getPosition(true);
                                $comments[] = $token;
                                $content = "";
                                if ($char !== null) {
                                    $content = $char;
                                    $this->index++;
                                }
                            }
                            break;
                        }
                    }
                } else {
                    break;
                }
                
            } else {
                break;
            }
        }
        
        if ($content !== "") {
            $this->adjustColumnAndLine($content);
        }
        
        return $comments;
    }
    
    protected function scanString($handleEscape = true)
    {
        $char = $this->charAt();
        if ($char === "'" || $char === '"') {
            $this->index++;
            $this->column++;
            $this->stringsStopsLSM->add($char);
            $buffer = $this->consumeUntil($this->stringsStopsLSM, $handleEscape);
            $this->stringsStopsLSM->remove($char);
            if ($buffer === null || $buffer[1] !== $char) {
                $this->error("Unterminated string");
            }
            return new Token(Token::TYPE_STRING_LITERAL, $char . $buffer[0]);
        }
        
        return null;
    }
    
    protected function scanTemplate()
    {
        $char = $this->charAt();
        
        $openCurly = isset($this->openBrackets["{"]) ?
                     $this->openBrackets["{"] :
                     0;
        
        $endExpression = false;
        if ($char === "}") {
            $len = count($this->openTemplates);
            if ($len && $this->openTemplates[$len - 1] === $openCurly) {
                $endExpression = true;
                array_pop($this->openTemplates);
            }
        }
        
        if ($char === "`" || $endExpression) {
            $this->index++;
            $this->column++;
            $buffer = $char;
            while (true) {
                $tempBuffer = $this->consumeUntil(array("`", "$"));
                if (!$tempBuffer) {
                    $this->error("Unterminated template");
                }
                $buffer .= $tempBuffer[0];
                if ($tempBuffer[1] !== "$" || $this->charAt() === "{") {
                    if ($tempBuffer[1] === "$") {
                        $this->index++;
                        $this->column++;
                        $buffer .= "{";
                        $this->openTemplates[] = $openCurly;
                    }
                    break;
                }
            }
            return new Token(Token::TYPE_TEMPLATE, $buffer);
        }
        
        return null;
    }
    
    protected function scanNumber()
    {
        $char = $this->charAt();
        if (!(($char >= "0" && $char <= "9") || $char === ".")) {
            return null;
        }

        $buffer = "";
        $allowedDecimals = true;
        
        if ($char !== ".") {
            
            $buffer = $this->consumeNumbers();
            $char = $this->charAt();
            
            if ($this->features->bigInt && $char === "n") {
                $this->index++;
                $this->column++;
                return new Token(Token::TYPE_BIGINT_LITERAL, $buffer . $char);
            }
            
            $lower = $char !== null ? strtolower($char) : null;
            
            if ($buffer === "0" && $lower !== null &&
                isset($this->{$lower . "numbers"})
            ) {
                
                $this->index++;
                $this->column++;
                $tempBuffer = $this->consumeNumbers($lower);
                if ($tempBuffer === null) {
                    $this->error("Missing numbers after 0$char");
                }
                $buffer .= $char . $tempBuffer;
                
                if ($this->consumeNumbers() !== null) {
                    $this->error();
                }

                if ($this->features->bigInt && $this->charAt() === "n") {
                    $this->index++;
                    $this->column++;
                    return new Token(Token::TYPE_BIGINT_LITERAL, $buffer . $char);
                }
                
                return new Token(Token::TYPE_NUMERIC_LITERAL, $buffer);
            }
            
            if ($tempBuffer = $this->consumeExponentPart()) {
                $buffer .= $tempBuffer;
                $allowedDecimals = false;
            }
        }
        
        if ($allowedDecimals && $this->charAt() === ".") {
            
            $this->index++;
            $this->column++;
            $buffer .= ".";
            
            $tempBuffer = $this->consumeNumbers();
            $buffer .= $tempBuffer;
            
            if ($buffer === ".") {
                $this->index--;
                $this->column--;
                return null;
            }
            
            if (($tempBuffer = $this->consumeExponentPart()) !== null) {
                $buffer .= $tempBuffer;
            }
        }
        
        return new Token(Token::TYPE_NUMERIC_LITERAL, $buffer);
    }
    
    protected function consumeNumbers($type = "", $max = null)
    {
        $buffer = "";
        $char = $this->charAt();
        $count = 0;
        $extra = $this->features->numericLiteralSeparator ? "_" : "";
        while (
            in_array($char, $this->{$type . "numbers"}) ||
            ($count && $char === $extra)
        ) {
            $buffer .= $char;
            $this->index++;
            $this->column++;
            $count ++;
            if ($count === $max) {
                break;
            }
            $char = $this->charAt();
        }
        if ($count && substr($buffer, -1) === "_") {
            $this->error(
                "Numeric separators are not allowed at the end of a number"
            );
        }
        return $count ? $buffer : null;
    }
    
    protected function consumeExponentPart()
    {
        $buffer = "";
        $char = $this->charAt();
        if ($char !== null && strtolower($char) === "e") {
            $this->index++;
            $this->column++;
            $buffer .= $char;
            $char = $this->charAt();
            if ($char === "+" || $char === "-") {
                $this->index++;
                $this->column++;
                $buffer .= $char;
            }
            $tempBuffer = $this->consumeNumbers();
            if ($tempBuffer === null) {
                $this->error("Missing exponent");
            }
            $buffer .= $tempBuffer;
        }
        return $buffer;
    }
    
    protected function scanPunctuator()
    {
        $token = null;
        $char = $this->charAt();
        
        if (isset($this->brackets[$char])) {
            if ($this->brackets[$char]) {
                $openBracket = $this->brackets[$char];
                if (!isset($this->openBrackets[$openBracket]) ||
                    !$this->openBrackets[$openBracket]
                ) {
                    if (!$this->isAfterSlash($this->getPosition(true))) {
                        $this->error();
                    }
                } else {
                    $this->openBrackets[$openBracket]--;
                }
            } else {
                if (!isset($this->openBrackets[$char])) {
                    $this->openBrackets[$char] = 0;
                }
                $this->openBrackets[$char]++;
            }
            $this->index++;
            $this->column++;
            $token = new Token(Token::TYPE_PUNCTUATOR, $char);
        } elseif (
            $match = $this->punctuatorsLSM->match($this, $this->index, $char)
        ) {
            if ($match[1] === "?." &&
                ($nextChar = $this->charAt($this->index + $match[0])) !== null &&
                $nextChar >= "0" && $nextChar <= "9"
            ) {
                $match = array(1, "?");
            }
            $this->index += $match[0];
            $this->column += $match[0];
            $token = new Token(Token::TYPE_PUNCTUATOR, $match[1]);
        }
        return $token;
    }
    
    protected function scanKeywordOrIdentifier()
    {
        if ($private = $this->features->privateMethodsAndFields && $this->charAt() === "#") {
            $this->index++;
            $this->column++;
        }

        $buffer = "";
        $start = true;
        while (($char = $this->charAt()) !== null) {
            if (
                ($char >= "a" && $char <= "z") ||
                ($char >= "A" && $char <= "Z") ||
                $char === "_" || $char === "$" ||
                (!$start && $char >= "0" && $char <= "9") ||
                $this->isIdentifierChar($char, $start)
            ) {
                $buffer .= $char;
                $this->index++;
                $this->column++;
            } elseif ($char === "\\" && ($seq = $this->consumeUnicodeEscapeSequence())) {
                if (!$this->isIdentifierChar($seq[1], $start)) {
                    break;
                }
                $buffer .= $seq[0];
            } else {
                break;
            }
            $start = false;
        }
        
        if ($buffer === "") {
            if ($private) {
                $this->index--;
                $this->column--;
            }
            return null;
        } elseif ($private) {
            $type = Token::TYPE_PRIVATE_IDENTIFIER;
            $buffer = "#" . $buffer;
        } elseif ($buffer === "null") {
            $type = Token::TYPE_NULL_LITERAL;
        } elseif ($buffer === "true" || $buffer === "false") {
            $type = Token::TYPE_BOOLEAN_LITERAL;
        } elseif (in_array($buffer, $this->keywords) ||
            in_array($buffer, $this->strictModeKeywords)
        ) {
            $type = Token::TYPE_KEYWORD;
        } else {
            $type = Token::TYPE_IDENTIFIER;
        }
        
        return new Token($type, $buffer);
    }
    
    protected function consumeUnicodeEscapeSequence()
    {
        if ($this->charAt() !== "\\" ||
            $this->charAt($this->index + 1) !== "u"
        ) {
            return null;
        }
        
        $startIndex = $this->index;
        $startColumn = $this->column;
        $this->index += 2;
        $this->column += 2;
        $brackets = false;
        if ($this->charAt() === "{") {
            $brackets = true;
            $this->index++;
            $this->column++;
            $code = $this->consumeNumbers("x");
            if ($code && $this->charAt() !== "}") {
                $code = null;
            } else {
                $this->index++;
                $this->column++;
            }
        } else {
            $code = $this->consumeNumbers("x", 4);
            if ($code && strlen($code) !== 4) {
                $code = null;
            }
        }
        
        if ($code === null) {
            $this->index = $startIndex;
            $this->column = $startColumn;
            return null;
        }
        
        return array(
            $brackets ? "\\u{" . $code . "}" : "\\u" . $code,
            Utils::unicodeToUtf8(hexdec($code))
        );
    }
    
    protected function isIdentifierChar($char, $start = true)
    {
        return ($char >= "a" && $char <= "z") ||
               ($char >= "A" && $char <= "Z") ||
               $char === "_" || $char === "$" ||
               (!$start && $char >= "0" && $char <= "9") ||
               preg_match($start ? $this->idStartRegex : $this->idPartRegex, $char);
    }
    
    protected function adjustColumnAndLine($buffer)
    {
        $lines = preg_split($this->linesSplitter, $buffer);
        $linesCount = count($lines) - 1;
        $this->line += $linesCount;
        $columns = mb_strlen($lines[$linesCount], "UTF-8");
        if ($linesCount) {
            $this->column = $columns;
        } else {
            $this->column += $columns;
        }
    }
    
    protected function consumeUntil(
        $stops, $handleEscape = true, $collectStop = true
    ) {
        $isLSM = $stops instanceof LSM;
        $buffer = "";
        $escaped = false;
        while (($char = $this->charAt()) !== null) {
            $incrIndex = 1;
            $isStop = false;
            if ($isLSM) {
                $m = $stops->match($this, $this->index, $char);
                if ($m) {
                    $isStop = true;
                    $incrIndex = $m[0];
                    $char = $m[1];
                }
            } else {
                $isStop = in_array($char, $stops);
            }
            $validStop = $isStop && !$escaped;
            if (!$validStop || $collectStop) {
                $this->index += $incrIndex;
                $buffer .= $char;
            }
            if ($validStop) {
                if (!$collectStop && $buffer === "") {
                    return null;
                }
                $this->adjustColumnAndLine($buffer);
                return array($buffer, $char);
            } elseif (!$escaped && $char === "\\" && $handleEscape) {
                $escaped = true;
            } else {
                $escaped = false;
            }
        }
        return null;
    }
}