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

class ExportSpecifier extends ModuleSpecifier
{
    protected $propertiesMap = array(
        "exported" => true
    );
    
    protected $exported;
    
    public function getExported()
    {
        return $this->exported;
    }
    
    public function setExported($exported)
    {
        $this->assertType($exported, array("Identifier", "StringLiteral"));
        $this->exported = $exported;
        return $this;
    }
}