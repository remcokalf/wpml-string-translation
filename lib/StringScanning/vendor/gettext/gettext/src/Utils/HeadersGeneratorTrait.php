<?php

namespace Gettext\Utils;

use Gettext\Translations;

trait HeadersGeneratorTrait
{
    protected static function generateHeaders(Translations $translations)
    {
        $headers = '';

        foreach ($translations->getHeaders() as $name => $value) {
            $headers .= sprintf("%s: %s\n", $name, $value);
        }

        return $headers;
    }
}
