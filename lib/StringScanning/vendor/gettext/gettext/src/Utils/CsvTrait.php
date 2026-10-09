<?php

namespace Gettext\Utils;

trait CsvTrait
{
    protected static $csvEscapeChar;

    protected static function supportsCsvEscapeChar()
    {
        if (static::$csvEscapeChar === null) {
            static::$csvEscapeChar = version_compare(PHP_VERSION, '5.5.4') >= 0;
        }

        return static::$csvEscapeChar;
    }

    protected static function fgetcsv($handle, $options)
    {
        if (static::supportsCsvEscapeChar()) {
            return fgetcsv($handle, 0, $options['delimiter'], $options['enclosure'], $options['escape_char']);
        }

        return fgetcsv($handle, 0, $options['delimiter'], $options['enclosure']);
    }

    protected static function fputcsv($handle, $fields, $options)
    {
        if (static::supportsCsvEscapeChar()) {
            return fputcsv($handle, $fields, $options['delimiter'], $options['enclosure'], $options['escape_char']);
        }

        return fputcsv($handle, $fields, $options['delimiter'], $options['enclosure']);
    }
}
