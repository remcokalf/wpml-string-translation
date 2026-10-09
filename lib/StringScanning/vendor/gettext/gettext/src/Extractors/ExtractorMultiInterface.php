<?php

namespace Gettext\Extractors;

use Gettext\Translations;

interface ExtractorMultiInterface
{
    public static function fromStringMultiple($string, array $translations, array $options = []);

    public static function fromFileMultiple($file, array $translations, array $options = []);
}
