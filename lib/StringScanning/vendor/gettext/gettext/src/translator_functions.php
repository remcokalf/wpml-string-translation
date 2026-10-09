<?php

use Gettext\BaseTranslator;

function __($original)
{
    $text = BaseTranslator::$current->gettext($original);

    if (func_num_args() === 1) {
        return $text;
    }

    $args = array_slice(func_get_args(), 1);

    return is_array($args[0]) ? strtr($text, $args[0]) : vsprintf($text, $args);
}

function noop__($original)
{
    return $original;
}

function n__($original, $plural, $value)
{
    $text = BaseTranslator::$current->ngettext($original, $plural, $value);

    if (func_num_args() === 3) {
        return $text;
    }

    $args = array_slice(func_get_args(), 3);

    return is_array($args[0]) ? strtr($text, $args[0]) : vsprintf($text, $args);
}

function p__($context, $original)
{
    $text = BaseTranslator::$current->pgettext($context, $original);

    if (func_num_args() === 2) {
        return $text;
    }

    $args = array_slice(func_get_args(), 2);

    return is_array($args[0]) ? strtr($text, $args[0]) : vsprintf($text, $args);
}

function d__($domain, $original)
{
    $text = BaseTranslator::$current->dgettext($domain, $original);

    if (func_num_args() === 2) {
        return $text;
    }

    $args = array_slice(func_get_args(), 2);

    return is_array($args[0]) ? strtr($text, $args[0]) : vsprintf($text, $args);
}

function dp__($domain, $context, $original)
{
    $text = BaseTranslator::$current->dpgettext($domain, $context, $original);

    if (func_num_args() === 3) {
        return $text;
    }

    $args = array_slice(func_get_args(), 3);

    return is_array($args[0]) ? strtr($text, $args[0]) : vsprintf($text, $args);
}

function dn__($domain, $original, $plural, $value)
{
    $text = BaseTranslator::$current->dngettext($domain, $original, $plural, $value);

    if (func_num_args() === 4) {
        return $text;
    }

    $args = array_slice(func_get_args(), 4);

    return is_array($args[0]) ? strtr($text, $args[0]) : vsprintf($text, $args);
}

function np__($context, $original, $plural, $value)
{
    $text = BaseTranslator::$current->npgettext($context, $original, $plural, $value);

    if (func_num_args() === 4) {
        return $text;
    }

    $args = array_slice(func_get_args(), 4);

    return is_array($args[0]) ? strtr($text, $args[0]) : vsprintf($text, $args);
}

function dnp__($domain, $context, $original, $plural, $value)
{
    $text = BaseTranslator::$current->dnpgettext($domain, $context, $original, $plural, $value);

    if (func_num_args() === 5) {
        return $text;
    }

    $args = array_slice(func_get_args(), 5);

    return is_array($args[0]) ? strtr($text, $args[0]) : vsprintf($text, $args);
}
