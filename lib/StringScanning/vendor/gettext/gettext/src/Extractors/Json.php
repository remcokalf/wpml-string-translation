<?php

namespace Gettext\Extractors;

use Gettext\Translations;
use Gettext\Utils\MultidimensionalArrayTrait;

class Json extends Extractor implements ExtractorInterface
{
    use MultidimensionalArrayTrait;

    public static function fromString($string, Translations $translations, array $options = [])
    {
        $messages = json_decode($string, true);

        if (is_array($messages)) {
            static::fromArray($messages, $translations);
        }
    }
}
