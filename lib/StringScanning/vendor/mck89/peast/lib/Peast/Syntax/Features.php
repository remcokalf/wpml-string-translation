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

class Features
{
    public $exponentiationOperator = false;

    public $asyncAwait = false;

    public $trailingCommaFunctionCallDeclaration = false;

    public $forInInitializer = false;

    public $asyncIterationGenerators = false;

    public $restSpreadProperties = false;

    public $skipEscapeSeqCheckInTaggedTemplates = false;

    public $optionalCatchBinding = false;

    public $paragraphLineSepInStrings = false;

    public $dynamicImport = false;

    public $bigInt = false;

    public $exportedNameInExportAll = false;

    public $importMeta = false;

    public $coalescingOperator = false;

    public $optionalChaining = false;

    public $logicalAssignmentOperators = false;

    public $numericLiteralSeparator = false;

    public $privateMethodsAndFields = false;

    public $classFields = false;

    public $classFieldsPrivateIn = false;

    public $topLevelAwait = false;

    public $classStaticBlock = false;

    public $arbitraryModuleNSNames = false;

    public $hashbangComments = false;

    public $importAttributes = false;
}