<?php

namespace Gettext\Utils;

use Gettext\Translations;

trait HeadersExtractorTrait
{
    protected static function extractHeaders($headers, Translations $translations)
    {
        $headers = explode("\n", $headers);
        $currentHeader = null;

        foreach ($headers as $line) {
            $line = static::convertString($line);

            if ($line === '') {
                continue;
            }

            if (static::isHeaderDefinition($line)) {
                $header = array_map('trim', explode(':', $line, 2));
                $currentHeader = $header[0];
                $translations->setHeader($currentHeader, $header[1]);
            } else {
                $entry = $translations->getHeader($currentHeader);
                $translations->setHeader($currentHeader, $entry.$line);
            }
        }
    }

    protected static function isHeaderDefinition($line)
    {
        return (bool) preg_match('/^[\w-]+:/', $line);
    }

    public static function convertString($value)
    {
        return $value;
    }
}
