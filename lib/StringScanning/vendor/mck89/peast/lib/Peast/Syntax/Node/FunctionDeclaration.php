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

class FunctionDeclaration extends Function_ implements Declaration
{
    public function setId($id)
    {
        $this->assertType($id, "Identifier");
        $this->id = $id;
        return $this;
    }
}