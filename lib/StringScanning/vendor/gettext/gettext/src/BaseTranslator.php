<?php

namespace Gettext;

abstract class BaseTranslator implements TranslatorInterface
{
    public static $current;

    public function noop($original)
    {
        return $original;
    }

    public function register()
    {
        $previous = static::$current;

        static::$current = $this;

        static::includeFunctions();

        return $previous;
    }

    public static function includeFunctions()
    {
        include_once __DIR__.'/translator_functions.php';
    }
}
