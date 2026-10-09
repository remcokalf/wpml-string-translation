<?php

namespace Gettext\Extractors;

use Gettext\Translations;

interface ExtractorInterface
{
    public static function fromFile($file, Translations $translations, array $options = []);

    public static function fromString($string, Translations $translations, array $options = []);
}
