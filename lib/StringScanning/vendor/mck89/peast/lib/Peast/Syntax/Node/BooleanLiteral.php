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

class BooleanLiteral extends Literal
{
    public function setValue($value)
    {
        if ($value === "true") {
            $this->value = true;
        } elseif ($value === "false") {
            $this->value = false;
        } else {
            $this->value = (bool) $value;
        }
        $this->raw = $this->value ? "true" : "false";
        return $this;
    }
}