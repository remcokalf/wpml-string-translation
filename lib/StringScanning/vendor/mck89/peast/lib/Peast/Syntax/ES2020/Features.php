<?php
/**
 * This file is part of the Peast package
 *
 * (c) Marco Marchiò <marco.mm89@gmail.com>
 *
 * For the full copyright and license information refer to the LICENSE file
 * distributed with this source code
 */
namespace Peast\Syntax\ES2020;

class Features extends \Peast\Syntax\ES2019\Features
{
    public $dynamicImport = true;

    public $bigInt = true;

    public $exportedNameInExportAll = true;

    public $importMeta = true;

    public $coalescingOperator = true;

    public $optionalChaining = true;
}