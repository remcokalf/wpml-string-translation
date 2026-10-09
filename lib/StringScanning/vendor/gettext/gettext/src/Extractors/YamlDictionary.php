<?php

namespace Gettext\Extractors;

use Gettext\Translations;
use Gettext\Utils\DictionaryTrait;
use Symfony\Component\Yaml\Yaml as YamlParser;

class YamlDictionary extends Extractor implements ExtractorInterface
{
    use DictionaryTrait;

    public static function fromString($string, Translations $translations, array $options = [])
    {
        $messages = YamlParser::parse($string);

        if (is_array($messages)) {
            static::fromArray($messages, $translations);
        }
    }
}
