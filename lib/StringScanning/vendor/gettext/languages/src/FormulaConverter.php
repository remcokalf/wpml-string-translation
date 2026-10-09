<?php

namespace Gettext\Languages;

use Exception;

class FormulaConverter
{
    public static function convertFormula($cldrFormula)
    {
        if (strpbrk($cldrFormula, '()') !== false) {
            throw new Exception("Unable to convert the formula '{$cldrFormula}': parenthesis handling not implemented");
        }
        $orSeparatedChunks = array();
        foreach (explode(' or ', $cldrFormula) as $cldrFormulaChunk) {
            $gettextFormulaChunk = null;
            $andSeparatedChunks = array();
            foreach (explode(' and ', $cldrFormulaChunk) as $cldrAtom) {
                $gettextAtom = self::convertAtom($cldrAtom);
                if ($gettextAtom === false) {
                    $gettextFormulaChunk = false;
                    break;
                }
                if ($gettextAtom !== true) {
                    $andSeparatedChunks[] = $gettextAtom;
                }
            }
            if (!isset($gettextFormulaChunk)) {
                if (empty($andSeparatedChunks)) {
                    $gettextFormulaChunk = true;
                } else {
                    $gettextFormulaChunk = implode(' && ', $andSeparatedChunks);
                    switch ($gettextFormulaChunk) {
                        case 'n >= 0 && n <= 2 && n != 2':
                            $gettextFormulaChunk = 'n == 0 || n == 1';
                            break;
                    }
                }
            }
            if ($gettextFormulaChunk === true) {
                return true;
            }
            if ($gettextFormulaChunk !== false) {
                $orSeparatedChunks[] = $gettextFormulaChunk;
            }
        }
        if (empty($orSeparatedChunks)) {
            return false;
        }

        return implode(' || ', $orSeparatedChunks);
    }

    private static function convertAtom($cldrAtom)
    {
        $m = null;
        $gettextAtom = $cldrAtom;
        $gettextAtom = str_replace(' = ', ' == ', $gettextAtom);
        $gettextAtom = str_replace('i', 'n', $gettextAtom);
        if (preg_match('/^n( % \d+)? (!=|==) \d+$/', $gettextAtom)) {
            return $gettextAtom;
        }
        if (preg_match('/^n( % \d+)? (!=|==) \d+(,\d+|\.\.\d+)+$/', $gettextAtom)) {
            return self::expandAtom($gettextAtom);
        }
        if (preg_match('/^(?:v|w)(?: % 10+)? == (\d+)(?:\.\.\d+)?$/', $gettextAtom, $m)) {
            return (int) $m[1] === 0 ? true : false;
        }
        if (preg_match('/^(?:v|w)(?: % 10+)? != (\d+)(?:\.\.\d+)?$/', $gettextAtom, $m)) {
            return (int) $m[1] === 0 ? false : true;
        }
        if (preg_match('/^(?:f|t|c|e)(?: % 10+)? == (\d+)(?:\.\.\d+)?$/', $gettextAtom, $m)) {
            return (int) $m[1] === 0 ? true : false;
        }
        if (preg_match('/^(?:f|t|c|e)(?: % 10+)? != (\d+)(?:\.\.\d+)?$/', $gettextAtom, $m)) {
            return (int) $m[1] === 0 ? false : true;
        }
        throw new Exception("Unable to convert the formula chunk '{$cldrAtom}' from CLDR to gettext");
    }

    private static function expandAtom($atom)
    {
        $m = null;
        if (preg_match('/^(n(?: % \d+)?) (==|!=) (\d+(?:\.\.\d+|,\d+)+)$/', $atom, $m)) {
            $what = $m[1];
            $op = $m[2];
            $chunks = array();
            foreach (explode(',', $m[3]) as $range) {
                $chunk = null;
                if ((!isset($chunk)) && preg_match('/^\d+$/', $range)) {
                    $chunk = "{$what} {$op} {$range}";
                }
                if ((!isset($chunk)) && preg_match('/^(\d+)\.\.(\d+)$/', $range, $m)) {
                    $from = (int) $m[1];
                    $to = (int) $m[2];
                    if (($to - $from) === 1) {
                        switch ($op) {
                            case '==':
                                $chunk = "({$what} == {$from} || {$what} == {$to})";
                                break;
                            case '!=':
                                $chunk = "{$what} != {$from} && {$what} == {$to}";
                                break;
                        }
                    } else {
                        switch ($op) {
                            case '==':
                                $chunk = "{$what} >= {$from} && {$what} <= {$to}";
                                break;
                            case '!=':
                                if ($what === 'n' && $from <= 0) {
                                    $chunk = "{$what} > {$to}";
                                } else {
                                    $chunk = "({$what} < {$from} || {$what} > {$to})";
                                }
                                break;
                        }
                    }
                }
                if (!isset($chunk)) {
                    throw new Exception("Unhandled range '{$range}' in '{$atom}'");
                }
                $chunks[] = $chunk;
            }
            if (count($chunks) === 1) {
                return $chunks[0];
            }
            switch ($op) {
                case '==':
                    return '(' . implode(' || ', $chunks) . ')';
                case '!=':
                    return implode(' && ', $chunks);
            }
        }
        throw new Exception("Unable to expand '{$atom}'");
    }
}
