<?php

namespace Gettext\Extractors;

use Gettext\Translations;
use Gettext\Utils\DictionaryTrait;

class JsonDictionary extends Extractor implements ExtractorInterface
{
    use DictionaryTrait;

    public static function fromString($string, Translations $translations, array $options = [])
    {
        $messages = json_decode($string, true);

        if (is_array($messages)) {
            static::fromArray($messages, $translations);
        }
    }
}
