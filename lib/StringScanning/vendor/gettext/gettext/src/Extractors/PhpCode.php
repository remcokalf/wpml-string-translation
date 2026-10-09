<?php

namespace Gettext\Extractors;

use Exception;
use Gettext\Translations;
use Gettext\Utils\FunctionsScanner;

class PhpCode extends Extractor implements ExtractorInterface, ExtractorMultiInterface
{
    public static $options = [
        'extractComments' => false,

        'constants' => [],

        'functions' => [
            'gettext' => 'gettext',
            '__' => 'gettext',
            'ngettext' => 'ngettext',
            'n__' => 'ngettext',
            'pgettext' => 'pgettext',
            'p__' => 'pgettext',
            'dgettext' => 'dgettext',
            'd__' => 'dgettext',
            'dngettext' => 'dngettext',
            'dn__' => 'dngettext',
            'dpgettext' => 'dpgettext',
            'dp__' => 'dpgettext',
            'npgettext' => 'npgettext',
            'np__' => 'npgettext',
            'dnpgettext' => 'dnpgettext',
            'dnp__' => 'dnpgettext',
            'noop' => 'noop',
            'noop__' => 'noop',
        ],
    ];

    protected static $functionsScannerClass = 'Gettext\Utils\PhpFunctionsScanner';

    public static function fromString($string, Translations $translations, array $options = [])
    {
        static::fromStringMultiple($string, [$translations], $options);
    }

    public static function fromStringMultiple($string, array $translations, array $options = [])
    {
        $options += static::$options;

        $functions = new static::$functionsScannerClass($string);

        if ($options['extractComments'] !== false) {
            $functions->enableCommentsExtraction($options['extractComments']);
        }

        $functions->saveGettextFunctions($translations, $options);
    }

    public static function fromFileMultiple($file, array $translations, array $options = [])
    {
        foreach (static::getFiles($file) as $file) {
            $options['file'] = $file;
            static::fromStringMultiple(static::readFile($file), $translations, $options);
        }
    }


    public static function convertString($value)
    {
        if (strpos($value, '\\') === false) {
            return substr($value, 1, -1);
        }

        if ($value[0] === "'") {
            return strtr(substr($value, 1, -1), ['\\\\' => '\\', '\\\'' => '\'']);
        }

        $value = substr($value, 1, -1);

        return preg_replace_callback(
            '/\\\(n|r|t|v|e|f|\$|"|\\\|x[0-9A-Fa-f]{1,2}|u{[0-9a-f]{1,6}}|[0-7]{1,3})/',
            function ($match) {
                switch ($match[1][0]) {
                    case 'n':
                        return "\n";
                    case 'r':
                        return "\r";
                    case 't':
                        return "\t";
                    case 'v':
                        return "\v";
                    case 'e':
                        return "\e";
                    case 'f':
                        return "\f";
                    case '$':
                        return '$';
                    case '"':
                        return '"';
                    case '\\':
                        return '\\';
                    case 'x':
                        return chr(hexdec(substr($match[1], 1)));
                    case 'u':
                        return static::unicodeChar(hexdec(substr($match[1], 1)));
                    default:
                        return chr(octdec($match[1]));
                }
            },
            $value
        );
    }

    protected static function unicodeChar($dec)
    {
        if ($dec < 0x80) {
            return chr($dec);
        }

        if ($dec < 0x0800) {
            return chr(0xC0 + ($dec >> 6))
                . chr(0x80 + ($dec & 0x3f));
        }

        if ($dec < 0x010000) {
            return chr(0xE0 + ($dec >> 12))
                . chr(0x80 + (($dec >> 6) & 0x3f))
                . chr(0x80 + ($dec & 0x3f));
        }

        if ($dec < 0x200000) {
            return chr(0xF0 + ($dec >> 18))
                . chr(0x80 + (($dec >> 12) & 0x3f))
                . chr(0x80 + (($dec >> 6) & 0x3f))
                . chr(0x80 + ($dec & 0x3f));
        }

        return null;
    }
}
