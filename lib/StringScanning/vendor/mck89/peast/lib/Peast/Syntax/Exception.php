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

class Exception extends \Exception
{
    protected $position;
    
    public function __construct($message, Position $position)
    {
        parent::__construct($message);
        $this->position = $position;
    }
    
    public function getPosition()
    {
        return $this->position;
    }
}