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

class ImportSpecifier extends ModuleSpecifier
{
    protected $propertiesMap = array(
        "imported" => true
    );
    
    protected $imported;
    
    public function getImported()
    {
        return $this->imported;
    }
    
    public function setImported($imported)
    {
        $this->assertType($imported, array("Identifier", "StringLiteral"));
        $this->imported = $imported;
        return $this;
    }
}