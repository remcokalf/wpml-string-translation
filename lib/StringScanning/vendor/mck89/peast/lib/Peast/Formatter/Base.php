<?php
/**
 * This file is part of the Peast package
 *
 * (c) Marco Marchiò <marco.mm89@gmail.com>
 *
 * For the full copyright and license information refer to the LICENSE file
 * distributed with this source code
 */
namespace Peast\Formatter;

abstract class Base
{
    protected $newLine = "\n";
    
    protected $indentation = "\t";
    
    protected $newLineBeforeCurlyBracket = false;
    
    protected $alwaysWrapBlocks = true;
    
    protected $spacesAroundOperators = true;
    
    protected $spacesInsideRoundBrackets = false;
    
    protected $renderComments = true;
    
    protected $recalcCommentsIndent = true;

    public function __construct($renderComments = false)
    {
        if ($this->renderComments) {
            $this->renderComments = $renderComments;
        }
    }
    
    public function getNewLine()
    {
        return $this->newLine;
    }
    
    public function getIndentation()
    {
        return $this->indentation;
    }
    
    public function getNewLineBeforeCurlyBracket()
    {
        return $this->newLineBeforeCurlyBracket;
    }
    
    public function getAlwaysWrapBlocks()
    {
        return $this->alwaysWrapBlocks;
    }
    
    public function getSpacesAroundOperator()
    {
        return $this->spacesAroundOperators;
    }
    
    public function getSpacesInsideRoundBrackets()
    {
        return $this->spacesInsideRoundBrackets;
    }
    
    public function getRenderComments()
    {
        return $this->renderComments;
    }
    
    public function getRecalcCommentsIndent()
    {
        return $this->recalcCommentsIndent;
    }
}