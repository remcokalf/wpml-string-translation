<?php

namespace Gettext\Generators;

use Gettext\Translations;
use Gettext\Utils\MultidimensionalArrayTrait;

class PhpArray extends Generator implements GeneratorInterface
{
    use MultidimensionalArrayTrait;

    public static $options = [
        'includeHeaders' => true,
    ];

    public static function toString(Translations $translations, array $options = [])
    {
        $array = static::generate($translations, $options);

        return '<?php return '.var_export($array, true).';';
    }

    public static function generate(Translations $translations, array $options = [])
    {
        $options += static::$options;

        return static::toArray($translations, $options['includeHeaders'], true);
    }
}
