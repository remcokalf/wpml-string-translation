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

class ExportDefaultDeclaration extends Node implements ModuleDeclaration
{
    protected $propertiesMap = array(
        "declaration" => true
    );
    
    protected $declaration;
    
    public function getDeclaration()
    {
        return $this->declaration;
    }
    
    public function setDeclaration($declaration)
    {
        $this->assertType($declaration, array("Declaration", "Expression"));
        $this->declaration = $declaration;
        return $this;
    }
}