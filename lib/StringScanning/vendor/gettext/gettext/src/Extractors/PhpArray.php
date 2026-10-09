<?php

namespace Gettext\Extractors;

use BadMethodCallException;
use Gettext\Translations;
use Gettext\Utils\MultidimensionalArrayTrait;

class PhpArray extends Extractor implements ExtractorInterface
{
    use MultidimensionalArrayTrait;

    public static function fromFile($file, Translations $translations, array $options = [])
    {
        foreach (static::getFiles($file) as $file) {
            static::fromArray(include($file), $translations);
        }
    }

    public static function fromString($string, Translations $translations, array $options = [])
    {
        throw new BadMethodCallException('PhpArray::fromString() cannot be called. Use PhpArray::fromFile()');
    }
}
