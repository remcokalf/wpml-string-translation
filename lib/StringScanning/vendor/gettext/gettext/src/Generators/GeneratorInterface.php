<?php

namespace Gettext\Generators;

use Gettext\Translations;

interface GeneratorInterface
{
    public static function toFile(Translations $translations, $file, array $options = []);

    public static function toString(Translations $translations, array $options = []);
}
