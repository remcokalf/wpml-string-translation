<?php
/**
 * This file is part of the Peast package
 *
 * (c) Marco Marchiò <marco.mm89@gmail.com>
 *
 * For the full copyright and license information refer to the LICENSE file
 * distributed with this source code
 */
namespace Peast;

use Peast\Syntax\Node\Comment;

class Renderer
{
    protected $formatter;
    
    protected $renderOpts;
    
    protected $noSemicolon = array(
        "ClassDeclaration",
        "ExportDefaultDeclaration",
        "ForInStatement",
        "ForOfStatement",
        "ForStatement",
        "FunctionDeclaration",
        "IfStatement",
        "LabeledStatement",
        "StaticBlock",
        "SwitchStatement",
        "TryStatement",
        "WhileStatement",
        "WithStatement",
        "MethodDefinition"
    );
    
    public function setFormatter(Formatter\Base $formatter)
    {
        $this->formatter = $formatter;
        
        $this->renderOpts = (object) array(
            "nl" => $this->formatter->getNewLine(),
            "ind" => $this->formatter->getIndentation(),
            "nlbc" => $this->formatter->getNewLineBeforeCurlyBracket(),
            "sao" =>  $this->formatter->getSpacesAroundOperator() ? " " : "",
            "sirb" => $this->formatter->getSpacesInsideRoundBrackets() ? " " : "",
            "awb" => $this->formatter->getAlwaysWrapBlocks(),
            "com" => $this->formatter->getRenderComments(),
            "rci" => $this->formatter->getRecalcCommentsIndent()
        );
        
        return $this;
    }
    
    public function getFormatter()
    {
        return $this->formatter;
    }
    
    public function render(Syntax\Node\Node $node)
    {
        if (!$this->formatter) {
            throw new \Exception("Formatter not set");
        }
        
        $this->renderOpts->indLevel = 0;
        
        return $this->renderNode($node);
    }
    
    protected function renderNode(Syntax\Node\Node $node, $addSemicolon = false)
    {
        $code = "";
        if ($this->renderOpts->com) {
            $code .= $this->renderComments($node);
        }
        $type = $node->getType();
        switch ($type) {
            case "ArrayExpression":
            case "ArrayPattern":
                $code .= "[" .
                         $this->joinNodes(
                            $node->getElements(),
                            "," . $this->renderOpts->sao
                         ) .
                         "]";
            break;
            case "ArrowFunctionExpression":
                if ($node->getAsync()) {
                    $code .= "async" . $this->renderOpts->sao;
                }
                $code .= "(" .
                         $this->renderOpts->sirb .
                         $this->joinNodes(
                            $node->getParams(),
                            "," . $this->renderOpts->sao
                         ) .
                         $this->renderOpts->sirb .
                         ")" .
                         $this->renderOpts->sao .
                         "=>";
                $body = $node->getBody();
                if ($body->getType() !== "BlockStatement") {
                    $code .= $this->renderOpts->sao . $this->renderNode($body);
                } else {
                    $code .= $this->renderStatementBlock($node, $body, true);
                }
            break;
            case "AwaitExpression":
                $code .= "await " . $this->renderNode($node->getArgument());
            break;
            case "AssignmentExpression":
            case "AssignmentPattern":
            case "BinaryExpression":
            case "LogicalExpression":
                $operator = $type === "AssignmentPattern" ?
                            "=" :
                            $node->getOperator();
                $code .= $this->renderNode($node->getLeft());
                $codeRight = $this->renderNode($node->getRight());
                if (preg_match("#^[a-z]+$#i", $operator)) {
                    $code .= " " .
                             $operator .
                             " ";
                } else {
                    $checkSpace = !$this->renderOpts->sao && $type === "BinaryExpression";
                    
                    if ($checkSpace && $code && substr($code, -1) === $operator) {
                        $code .= " ";
                    }
                    
                    $code .= $this->renderOpts->sao .
                             $operator .
                             $this->renderOpts->sao;
                    
                    if ($checkSpace && $codeRight && $codeRight[0] === $operator) {
                        $code .= " ";
                    }
                }
                $code .= $codeRight;
            break;
            case "BlockStatement":
            case "ClassBody":
            case "Program":
                $code .= $this->renderStatementBlock(
                    $node, $node->getBody(), false, false, true, false
                );
            break;
            case "BreakStatement":
            case "ContinueStatement":
                $code .= $type === "BreakStatement" ? "break" : "continue";
                if ($label = $node->getLabel()) {
                    $code .= " " . $this->renderNode($label);
                }
            break;
            case "CallExpression":
            case "NewExpression":
                if ($type === "NewExpression") {
                    $code .= "new ";
                    $optional = false;
                } else {
                    $optional = $node->getOptional();
                }
                $code .= $this->renderNode($node->getCallee()) .
                         ($optional ? "?." : "") .
                         "(" .
                         $this->renderOpts->sirb .
                         $this->joinNodes(
                            $node->getArguments(),
                            "," . $this->renderOpts->sao
                         ) .
                         $this->renderOpts->sirb .
                         ")";
            break;
            case "CatchClause":
                $code .= "catch";
                if ($params = $node->getParam()) {
                    $code .= $this->renderOpts->sao .
                             "(" .
                             $this->renderOpts->sirb .
                             $this->renderNode($params) .
                             $this->renderOpts->sirb .
                             ")";
                }
                $code .= $this->renderStatementBlock($node, $node->getBody(), true);
            break;
            case "ChainExpression":
            case "ExpressionStatement":
                $code .= $this->renderNode($node->getExpression());
            break;
            case "ClassExpression":
            case "ClassDeclaration":
                $code .= "class";
                if ($id = $node->getId()) {
                    $code .= " " . $this->renderNode($id);
                }
                if ($superClass = $node->getSuperClass()) {
                    $code .= " extends " . $this->renderNode($superClass);
                }
                $code .= $this->renderStatementBlock(
                    $node, $node->getBody(), true
                );
            break;
            case "ConditionalExpression":
                $code .= $this->renderNode($node->getTest()) .
                         $this->renderOpts->sao .
                         "?" .
                         $this->renderOpts->sao .
                         $this->renderNode($node->getConsequent()) .
                         $this->renderOpts->sao .
                         ":" .
                         $this->renderOpts->sao .
                         $this->renderNode($node->getAlternate());
            break;
            case "DebuggerStatement":
                $code .= "debugger";
            break;
            case "DoWhileStatement":
                $code .= "do" .
                         $this->renderStatementBlock(
                            $node, $node->getBody(), null, true
                         ) .
                         $this->renderOpts->sao .
                         "while" .
                         $this->renderOpts->sao .
                         "(" .
                         $this->renderOpts->sirb .
                         $this->renderNode($node->getTest()) .
                         $this->renderOpts->sirb .
                         ")";
            break;
            case "JSXEmptyExpression":
            case "EmptyStatement":
            break;
            case "ExportAllDeclaration":
                $code .= "export *";
                $exported = $node->getExported();
                if ($exported) {
                    $code .= " as " . $this->renderNode($exported);
                }
                $code .= " from " . $this->renderNode($node->getSource());
                $code .= $this->renderImportAttributes($node);
            break;
            case "ExportDefaultDeclaration":
                $declaration = $node->getDeclaration();
                $code .= "export default " .
                         $this->renderNode($declaration);
                if ($this->requiresSemicolon($declaration)) {
                    $code .= ";";
                }
            break;
            case "ExportNamedDeclaration":
                $code .= "export";
                if ($dec = $node->getDeclaration()) {
                    $code .= " " .
                             $this->renderNode($dec);
                } else {
                    $code .= $this->renderOpts->sao .
                             "{" .
                             $this->joinNodes(
                                 $node->getSpecifiers(),
                                 "," . $this->renderOpts->sao
                             ) .
                             "}";
                    if ($source = $node->getSource()) {
                        $code .= $this->renderOpts->sao .
                                 "from " .
                                 $this->renderNode($source);
                    }
                    $code .= $this->renderImportAttributes($node);
                }
            break;
            case "ExportSpecifier":
                $local = $this->renderNode($node->getLocal());
                $ref = $this->renderNode($node->getExported());
                $code .= $local === $ref ?
                         $local :
                         $local . " as " . $ref;
            break;
            case "ForInStatement":
            case "ForOfStatement":
                $this->renderOpts->forceSingleLine = true;
                $code .= "for" .
                         ($type === "ForOfStatement" && $node->getAwait() ? " await" : "") .
                         $this->renderOpts->sao .
                         "(" .
                         $this->renderOpts->sirb .
                         $this->renderNode($node->getLeft()) .
                         " " . ($type === "ForInStatement" ? "in" : "of") . " " .
                         $this->renderNode($node->getRight()) .
                         $this->renderOpts->sirb .
                         ")" .
                         $this->renderStatementBlock($node, $node->getBody());
                unset($this->renderOpts->forceSingleLine);
            break;
            case "ForStatement":
                $this->renderOpts->forceSingleLine = true;
                $code .= "for" .
                         $this->renderOpts->sao .
                         "(" .
                         $this->renderOpts->sirb;
                if ($init = $node->getInit()) {
                    $code .= $this->renderNode($init);
                }
                $code .= ";" . $this->renderOpts->sao;
                if ($test = $node->getTest()) {
                    $code .= $this->renderNode($test);
                }
                $code .= ";" . $this->renderOpts->sao;
                if ($update = $node->getUpdate()) {
                    $code .= $this->renderNode($update);
                }
                $code .= $this->renderOpts->sirb .
                         ")" .
                         $this->renderStatementBlock($node, $node->getBody());
                unset($this->renderOpts->forceSingleLine);
            break;
            case "FunctionDeclaration":
            case "FunctionExpression":
                $id = $node->getId();
                if ($node->getAsync()) {
                    $code .= "async ";
                }
                $code .= "function";
                if ($node->getGenerator()) {
                    $code .= $this->renderOpts->sao .
                             "*";
                } elseif ($id) {
                    $code .= " ";
                }
                if ($id) {
                    if ($node->getGenerator()) {
                        $code .= $this->renderOpts->sao;
                    }
                    $code .= $this->renderNode($id);
                }
                $code .= $this->renderOpts->sao .
                         "(" .
                         $this->renderOpts->sirb .
                         $this->joinNodes(
                            $node->getParams(),
                            "," . $this->renderOpts->sao
                         ) .
                         $this->renderOpts->sirb .
                         ")" .
                         $this->renderStatementBlock($node, $node->getBody(), true);
            break;
            case "ImportExpression":
                $options = $node->getOptions();
                $code .= "import(" .
                         $this->renderOpts->sirb .
                         $this->renderNode($node->getSource()) .
                         (
                            $options ?
                            "," . $this->renderOpts->sao . $this->renderNode($options) :
                            ""
                         ) .
                         $this->renderOpts->sirb .
                         ")";
            break;
            case "JSXIdentifier":
            case "Identifier":
                $code .= $node->getRawName();
            break;
            case "IfStatement":
                $code .= "if" .
                         $this->renderOpts->sao .
                         "(" .
                         $this->renderOpts->sirb .
                         $this->renderNode($node->getTest()) .
                         $this->renderOpts->sirb .
                         ")";
                
                $code .= $this->renderStatementBlock(
                    $node, $node->getConsequent()
                );
                if ($alternate = $node->getAlternate()) {
                    $code .= $this->renderOpts->sao .
                             "else" .
                             $this->renderStatementBlock(
                                 $node,
                                 $alternate,
                                 null,
                                 true
                             );
                }
            break;
            case "ImportAttribute":
                $code .= $this->renderNode($node->getKey()) .
                         ":" .
                         $this->renderOpts->sao .
                         $this->renderNode($node->getValue());
            break;
            case "ImportDeclaration":
                $code .= "import ";
                $specifiers = $node->getSpecifiers();
                if (count($specifiers)) {
                    $sep = "," . $this->renderOpts->sao;
                    $groups = $parts = array();
                    foreach ($specifiers as $spec) {
                        $specType = $spec->getType();
                        if (!isset($groups[$specType])) {
                            $groups[$specType] = array();
                        }
                        $groups[$specType][] = $spec;
                    }
                    if (isset($groups["ImportDefaultSpecifier"])) {
                        foreach ($groups["ImportDefaultSpecifier"] as $s) {
                            $parts[] = $this->renderNode($s);
                        }
                    }
                    if (isset($groups["ImportNamespaceSpecifier"])) {
                        foreach ($groups["ImportNamespaceSpecifier"] as $s) {
                            $parts[] = $this->renderNode($s);
                        }
                    }
                    if (isset($groups["ImportSpecifier"])) {
                        $impSpec = array();
                        foreach ($groups["ImportSpecifier"] as $s) {
                            $impSpec[] = $this->renderNode($s);
                        }
                        $parts[] = "{" . implode($sep, $impSpec) . "}";
                    }
                    $code .= implode($sep, $parts) . " from ";
                }
                $code .= $this->renderNode($node->getSource());
                $code .= $this->renderImportAttributes($node);
            break;
            case "ImportDefaultSpecifier":
                $code .= $this->renderNode($node->getLocal());
            break;
            case "ImportNamespaceSpecifier":
                $code .= "* as " . $this->renderNode($node->getLocal());
            break;
            case "ImportSpecifier":
                $local = $this->renderNode($node->getLocal());
                $ref = $this->renderNode($node->getImported());
                $code .= $local === $ref ?
                         $local :
                         $ref . " as " . $local;
            break;
            case "JSXAttribute":
                $code .= $this->renderNode($node->getName());
                if ($value = $node->getValue()) {
                    $code .= "=" . $this->renderNode($value);
                }
            break;
            case "JSXClosingElement":
                $code .= "</" . $this->renderNode($node->getName()) . ">";
            break;
            case "JSXClosingFragment":
                $code .= "</>";
            break;
            case "JSXElement":
                $code .= $this->renderNode($node->getOpeningElement()) .
                         $this->joinNodes($node->getChildren(), "");
                if ($closing = $node->getClosingElement()) {
                    $code .= $this->renderNode($closing);
                }
            break;
            case "JSXExpressionContainer":
                $code .= "{" . $this->renderNode($node->getExpression()) . "}";
            break;
            case "JSXFragment":
                $code .= $this->renderNode($node->getOpeningFragment()) .
                         $this->joinNodes($node->getChildren(), "") .
                         $this->renderNode($node->getClosingFragment());
            break;
            case "JSXNamespacedName":
                $code .= $this->renderNode($node->getNamespace()) . ":" .
                         $this->renderNode($node->getName());
            break;
            case "JSXOpeningElement":
                $code .= "<" . $this->renderNode($node->getName());
                $attributes = $node->getAttributes();
                if (count($attributes)) {
                    $code .= " " . $this->joinNodes($attributes, " ");
                }
                if ($node->getSelfClosing()) {
                    $code .= "/";
                }
                $code .= ">";
            break;
            case "JSXOpeningFragment":
                $code .= "<>";
            break;
            case "JSXSpreadAttribute":
                $code .= "{..." . $this->renderNode($node->getArgument()) . "}";
            break;
            case "JSXSpreadChild":
                $code .= "{..." . $this->renderNode($node->getExpression()) . "}";
            break;
            case "LabeledStatement":
                $body = $node->getBody();
                $code .= $this->renderNode($node->getLabel()) .
                         ":";
                if ($body->getType() === "BlockStatement") {
                    $code .= $this->renderStatementBlock($node, $body, true);
                } else {
                    $code .= $this->renderOpts->nl .
                             $this->getIndentation() .
                             $this->renderNode($body);
                }
                if ($this->requiresSemicolon($body)) {
                    $code .= ";";
                }
            break;
            case "JSXText":
            case "Literal":
            case "RegExpLiteral":
                $code .= $node->getRaw();
            break;
            case "JSXMemberExpression":
            case "MemberExpression":
                $property = $node->getProperty();
                $compiledProperty = $this->renderNode($property);
                $code .= $this->renderNode($node->getObject());
                $optional = false;
                if ($type === "MemberExpression") {
                    $optional = $node->getOptional();
                }
                $propertyType = $property->getType();
                if ($type === "MemberExpression" &&
                    ($node->getComputed() ||
                    ($propertyType !== "Identifier" && $propertyType !== "PrivateIdentifier"))) {
                    $code .= ($optional ? "?." : "") . "[" . $compiledProperty . "]";
                } else {
                    $code .= ($optional ? "?." : ".") . $compiledProperty;
                }
            break;
            case "MetaProperty":
                $code .= $node->getMeta() . "." . $node->getProperty();
            break;
            case "MethodDefinition":
                if ($node->getStatic()) {
                    $code .= "static ";
                }
                $value = $node->getValue();
                $key = $node->getKey();
                $kind = $node->getKind();
                if ($kind === $node::KIND_GET || $kind === $node::KIND_SET) {
                    $code .= $kind . " ";
                } else {
                    if ($value->getAsync()) {
                        $code .= "async ";
                    }
                    if ($value->getGenerator()) {
                        $code .= "*" .
                                 $this->renderOpts->sao;
                    }
                }
                if ($node->getComputed()) {
                    $code .= "[" .
                             $this->renderNode($key) .
                             "]";
                } else {
                    $code .= $this->renderNode($key);
                }
                $code .= $this->renderOpts->sao .
                         preg_replace("/^[^(]+/", "", $this->renderNode($value));
            break;
            case "ObjectExpression":
                $currentIndentation = $this->getIndentation();
                $this->renderOpts->indLevel++;
                $indentation = $this->getIndentation();
                if (isset($this->renderOpts->forceSingleLine)) {
                    $start = $end = "";
                    $separator = "," . $this->renderOpts->sao;
                } else {
                    $end = $this->renderOpts->nl . $currentIndentation;
                    $start = $this->renderOpts->nl . $indentation;
                    $separator = "," . $this->renderOpts->nl . $indentation;
                }
                $code .= "{";
                $properties = $node->getProperties();
                if (count($properties)) {
                    $code .= $start .
                             $this->joinNodes(
                                $properties,
                                $separator
                             ) .
                             $end;
                }
                $code .= "}";
                $this->renderOpts->indLevel--;
            break;
            case "ObjectPattern":
                $code .= "{" .
                         $this->joinNodes(
                            $node->getProperties(),
                            "," . $this->renderOpts->sao
                         ) .
                         "}";
            break;
            case "ParenthesizedExpression":
                $code .= "(" .
                         $this->renderOpts->sirb .
                         $this->renderNode($node->getExpression()) .
                         $this->renderOpts->sirb .
                         ")";
            break;
            case "PrivateIdentifier":
                $code .= "#" . $node->getName();
            break;
            case "Property":
                $value = $node->getValue();
                $key = $node->getKey();
                $compiledKey = $this->renderNode($key);
                $compiledValue = $this->renderNode($value);
                $keyType = $key->getType();
                $valueType = $value->getType();
                if ($valueType === "AssignmentPattern" &&
                    $compiledKey === $this->renderNode($value->getLeft())) {
                    $code .= $compiledValue;
                } else {
                    $kind = $node->getKind();
                    $getterSetter = $kind === $node::KIND_GET ||
                                    $kind === $node::KIND_SET;
                    if ($getterSetter) {
                        $code .= $kind . " ";
                    } elseif ($value->getType() === "FunctionExpression" &&
                              $value->getGenerator()) {
                        $code .= "*" .
                                 $this->renderOpts->sao;
                    }
                    if ($node->getMethod() && $value->getAsync()) {
                        $code .= "async ";
                    }
                    if ($node->getComputed()) {
                        $code .= "[" . $compiledKey . "]";
                    } else {
                        $code .= $compiledKey;
                    }
                    if ($node->getMethod() || $getterSetter) {
                        $code .= $this->renderOpts->sao .
                                 preg_replace("/^[^(]+/", "", $compiledValue);
                    } elseif ($keyType !== "Identifier" ||
                              $valueType !== "Identifier" ||
                              $compiledKey !== $compiledValue
                    ) {
                        $code .= ($node->getShorthand() ? "=" : ":") .
                                 $this->renderOpts->sao .
                                 $compiledValue;
                    }
                }
            break;
            case "PropertyDefinition":
                if ($node->getStatic()) {
                    $code .= "static ";
                }
                $compiledKey = $this->renderNode($node->getKey());
                if ($node->getComputed()) {
                    $code .= "[" . $compiledKey . "]";
                } else {
                    $code .= $compiledKey;
                }
                if ($value = $node->getValue()) {
                    $code .= $this->renderOpts->sao .
                             "=" .
                             $this->renderOpts->sao .
                             $this->renderNode($value);
                }
            break;
            case "RestElement":
            case "SpreadElement":
                $code .= "..." . $this->renderNode($node->getArgument());
            break;
            case "ReturnStatement":
                $code .= "return";
                if ($argument = $node->getArgument()) {
                    $code .= " " . $this->renderNode($argument);
                }
            break;
            case "SequenceExpression":
                $code .= $this->joinNodes(
                            $node->getExpressions(),
                            "," . $this->renderOpts->sao
                         );
            break;
            case "StaticBlock":
                $code .= "static";
                $code .= $this->renderStatementBlock($node, $node->getBody(), true);
            break;
            case "Super":
                $code .= "super";
            break;
            case "SwitchCase":
                if ($test = $node->getTest()) {
                    $code .= "case " . $this->renderNode($test);
                } else {
                    $code .= "default";
                }
                $code .= ":";
                if (count($node->getConsequent())) {
                    $code .= $this->renderStatementBlock(
                                 $node,
                                 $node->getConsequent()
                             );
                }
            break;
            case "SwitchStatement":
                $code .= "switch" .
                         $this->renderOpts->sao .
                         "(" .
                         $this->renderOpts->sirb .
                         $this->renderNode($node->getDiscriminant()) .
                         $this->renderOpts->sirb .
                         ")" .
                         $this->renderStatementBlock(
                             $node, $node->getCases(), true, false, false
                         );
            break;
            case "TaggedTemplateExpression":
                $code .= $this->renderNode($node->getTag()) .
                         $this->renderNode($node->getQuasi());
            break;
            case "TemplateElement":
                $code .= $node->getRawValue();
            break;
            case "TemplateLiteral":
                $code .= "`";
                foreach ($node->getParts() as $part) {
                    if ($part->getType() === "TemplateElement") {
                        $code .= $this->renderNode($part);
                    } else {
                        $code .= "$" . "{" . $this->renderNode($part) . "}";
                    }
                }
                $code .= "`";
            break;
            case "ThisExpression":
                $code .= "this";
            break;
            case "ThrowStatement":
                $code .= "throw " . $this->renderNode($node->getArgument());
            break;
            case "TryStatement":
                $code .= "try" .
                         $this->renderStatementBlock($node, $node->getBlock(), true);
                if ($handler = $node->getHandler()) {
                    $code .= $this->renderOpts->sao .
                             $this->renderNode($handler);
                }
                if ($finalizer = $node->getFinalizer()) {
                    $code .= $this->renderOpts->sao .
                             "finally" .
                             $this->renderStatementBlock($node, $finalizer, true);
                }
            break;
            case "UnaryExpression":
            case "UpdateExpression":
                $prefix = $node->getPrefix();
                if ($prefix) {
                    $code .= $node->getOperator();
                    if (preg_match("#^[a-z]+$#i", $node->getOperator())) {
                        $code .= " ";
                    }
                }
                $code .= $this->renderNode($node->getArgument());
                if (!$prefix) {
                    $code .= $node->getOperator();
                }
            break;
            case "VariableDeclaration":
                $this->renderOpts->indLevel++;
                $indentation = $this->getIndentation();
                if (isset($this->renderOpts->forceSingleLine)) {
                    $separator = "," . $this->renderOpts->sao;
                } else {
                    $separator = "," . $this->renderOpts->nl . $indentation;
                }
                $code .= $node->getKind() .
                         " " .
                         $this->joinNodes(
                            $node->getDeclarations(),
                            $separator
                         );
                $this->renderOpts->indLevel--;
            break;
            case "VariableDeclarator":
                $code .= $this->renderNode($node->getId());
                if ($init = $node->getInit()) {
                    $code .= $this->renderOpts->sao .
                             "=" .
                             $this->renderOpts->sao .
                             $this->renderNode($init);
                }
            break;
            case "WhileStatement":
                $code .= "while" .
                         $this->renderOpts->sao .
                         "(" .
                         $this->renderOpts->sirb .
                         $this->renderNode($node->getTest()) .
                         $this->renderOpts->sirb .
                         ")" .
                         $this->renderStatementBlock($node, $node->getBody());
            break;
            case "WithStatement":
                $code .= "with" .
                         $this->renderOpts->sao .
                         "(" .
                         $this->renderOpts->sirb .
                         $this->renderNode($node->getObject()) .
                         $this->renderOpts->sirb .
                         ")" .
                         $this->renderStatementBlock($node, $node->getBody());
            break;
            case "YieldExpression":
                $code .= "yield";
                if ($node->getDelegate()) {
                    $code .= " *";
                }
                if ($argument = $node->getArgument()) {
                    $code .= " " . $this->renderNode($argument);
                }
            break;
        }
        if ($addSemicolon) {
            $code .= ";";
        }
        if ($this->renderOpts->com) {
            $code .= $this->renderComments($node, false);
        }
        return $code;
    }
    
    protected function renderStatementBlock(
        $parent, $node, $forceBrackets = null, $mandatorySeparator = false,
        $addSemicolons = true, $incIndent = true
    ) {
        $code = "";
        
        if (is_array($node) && count($node) === 1) {
            $node = $node[0];
        }

        $origNode = null;
        if (!is_array($node) &&
            in_array($node->getType(), array("BlockStatement", "ClassBody"))) {
            $origNode = $node;
            $node = $node->getBody();
        }
        
        if ($forceBrackets !== null) {
            $hasBrackets = $forceBrackets;
        } else {
            $hasBrackets = $this->needsBrackets($parent, $node);
        }
        $currentIndentation = $this->getIndentation();
        
        if ($hasBrackets) {
            if ($this->renderOpts->nlbc) {
                $code .= $this->renderOpts->nl . $currentIndentation;
            } else {
                $code .= $this->renderOpts->sao;
            }
        }
        elseif ($parent->getType() === "SwitchCase") {
            $code .= $this->renderOpts->nl;
        }

        $emptyBody = is_array($node) && !count($node);

        if ($this->renderOpts->com && $origNode) {
            $code .= $this->renderComments($origNode, true, !$emptyBody);
        }
        
        if ($hasBrackets) {
            $code .= "{" . $this->renderOpts->nl;
        } elseif ($mandatorySeparator) {
            $code .= " ";
        }
        
        if ($incIndent) {
            $this->renderOpts->indLevel++;
        }
        $subIndentation = $this->getIndentation();
        
        if (is_array($node)) {
            if (!$emptyBody) {
                $code .= $subIndentation .
                         $this->joinNodes(
                            $node,
                            $this->renderOpts->nl . $subIndentation,
                            $addSemicolons
                         );
            }
        } else {
            $code .= $subIndentation . $this->renderNode(
                $node,
                $addSemicolons && $this->requiresSemicolon($node)
            );
        }
        
        if ($this->renderOpts->com) {
            if (!$emptyBody) {
                $code = $this->trimEmptyLine($code);
            }
            if ($origNode) {
                $code .= $this->renderComments($origNode, false, !$emptyBody);
                if (!$emptyBody) {
                    $code = $this->trimEmptyLine($code);
                }
            }
        }
        
        if ($incIndent) {
            $this->renderOpts->indLevel--;
        }
        
        if ($hasBrackets) {
            if (!$emptyBody) {
                $code .= $this->renderOpts->nl;
            }
            $code .= $currentIndentation . "}";
        }
        
        return $code;
    }
    
    protected function joinNodes($nodes, $separator, $addSemicolons=false)
    {
        $parts = array();
        foreach ($nodes as $node) {
            if (!$node) {
                $code = "";
            } else {
                $code = $this->renderNode(
                    $node,
                    $addSemicolons && $this->requiresSemicolon($node)
                );
            }
            $parts[] = $code;
        }
        return implode($separator, $parts);
    }
    
    protected function needsBrackets($parent, $node)
    {
        $parentType = $parent->getType();
        $inSwitchCase = $parentType === "SwitchCase";
        if (!$inSwitchCase && ($this->renderOpts->awb || (is_array($node) && count($node) !== 1))) {
            return true;
        }
        if (!is_array($node)) {
            $node = array($node);
        }
        $addBrackets = false;
        $optBracketNodes = array(
            "DoWhileStatement", "ForInStatement", "ForOfStatement",
            "ForStatement", "WhileStatement", "WithStatement"
        );
        $inIfWithElse = $parentType === "IfStatement" && $parent->getAlternate();
        $checkFn = function ($n) use ($inIfWithElse, $optBracketNodes, &$addBrackets) {
            $type = $n->getType();
            if ($inIfWithElse && $type === "IfStatement") {
                if (!$n->getAlternate()) {
                    $addBrackets = true;
                }
                return Traverser::DONT_TRAVERSE_CHILD_NODES;
            } elseif ($type === "BlockStatement") {
                if (count($n->getBody()) !== 1) {
                    return Traverser::DONT_TRAVERSE_CHILD_NODES;
                }
            } elseif ($inIfWithElse && $type === "LabeledStatement") {
                if ($n->getBody()->getType() === "BlockStatement") {
                    $addBrackets = true;
                }
                return Traverser::DONT_TRAVERSE_CHILD_NODES;
            } elseif ($type === "VariableDeclaration") {
                if (in_array($n->getKind(), array($n::KIND_LET, $n::KIND_CONST))) {
                    $addBrackets = true;
                }
                return Traverser::DONT_TRAVERSE_CHILD_NODES;
            } elseif (!in_array($type, $optBracketNodes)) {
                return Traverser::DONT_TRAVERSE_CHILD_NODES;
            }
        };
        foreach ($node as $n) {
            $n->traverse($checkFn);
            if ($addBrackets) {
                break;
            }
        }
        return $addBrackets;
    }

    protected function renderComments($node, $leading = true, $blockContent = null)
    {
        $code = "";
        $fn = $leading ? "getLeadingComments" : "getTrailingComments";
        $comments = $node ? $node->$fn() : array();
        $numComments = count($comments);
        if ($numComments) {
            $lastFormatted = $blockContent === false ? true : $leading;
            $refNode = $node;
            $refKey = $leading ? "end" : "start";
            $refNodeKey = $leading ? "start" : "end";
            $indent = $this->getIndentation();
            foreach ($comments as $k => $comment) {                
                $lastComment = $k === $numComments - 1;
                $isMultilineComment = $comment->getKind() === Comment::KIND_MULTILINE;
                $format = true;
                if (
                    $refNode && $isMultilineComment &&
                    $comment->location && $refNode->location &&
                    $comment->location->$refKey->getLine() === $refNode->location->$refNodeKey->getLine()
                ) {
                    $format = false;
                }
                if ($format && !$lastFormatted) {
                    $code .= $this->renderOpts->nl . $indent;
                }
                if ($format && $blockContent === false && !$k) {
                    $code .= $indent;
                }

                $commentRaw = $comment->getRawText();

                if ($isMultilineComment && $indent && $this->renderOpts->rci) {
                    $commentRaw = preg_replace("/^\s+\*/m", $indent . " *", $commentRaw);
                }

                $code .= $commentRaw;

                if ($format && ($blockContent !== true || !$lastComment || !$isMultilineComment)) {
                    $code .= !$isMultilineComment && !$this->renderOpts->nl ? "\n" : $this->renderOpts->nl;
                    if ($blockContent === null || !$lastComment) {
                        $code .= $indent;
                    }
                }
                $refNode = $comment;
                $lastFormatted = $format;
            }
        }
        return $code;
    }

    protected function renderImportAttributes($node)
    {
        $code = "";
        if (count($node->getAttributes())) {
            $code .= " with" .
                     $this->renderOpts->sao .
                     "{" .
                     $this->joinNodes(
                        $node->getAttributes(),
                        "," . $this->renderOpts->sao
                     ) .
                     "}";
        }
        return $code;
    }

    protected function trimEmptyLine($code)
    {
        if ($this->renderOpts->nl) {
            $nl = preg_quote($this->renderOpts->nl, "/");
            $indent = preg_quote($this->getIndentation(), "/");
            $code = preg_replace("/$nl(?:$indent)?$/", "", $code);
        }
        return $code;
    }
    
    protected function requiresSemicolon($node)
    {
        return !in_array($node->getType(), $this->noSemicolon);
    }
    
    protected function getIndentation()
    {
        return str_repeat(
            $this->renderOpts->ind,
            $this->renderOpts->indLevel
        );
    }
}