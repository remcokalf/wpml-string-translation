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

class ForOfStatement extends ForInStatement
{
    protected $propertiesMap = array(
        "left" => true,
        "right" => true,
        "body" => true,
        "await" => false
    );
    
    protected $await = false;
    
    public function getAwait()
    {
        return $this->await;
    }
    
    public function setAwait($await)
    {
        $this->await = (bool) $await;
        return $this;
    }
}