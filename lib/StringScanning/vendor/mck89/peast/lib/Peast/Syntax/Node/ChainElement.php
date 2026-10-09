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

abstract class ChainElement extends Node implements Expression
{
    protected $propertiesMap = array(
        "optional" => false
    );

    protected $optional = false;

    public function getOptional()
    {
        return $this->optional;
    }

    public function setOptional($optional)
    {
        $this->optional = (bool) $optional;
        return $this;
    }
}