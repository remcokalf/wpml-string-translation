<?php

namespace Gettext\Generators;

use Gettext\Translations;
use Gettext\Utils\HeadersGeneratorTrait;

class Mo extends Generator implements GeneratorInterface
{
    use HeadersGeneratorTrait;

    public static $options = [
        'includeHeaders' => true,
    ];

    public static function toString(Translations $translations, array $options = [])
    {
        $options += static::$options;
        $messages = [];

        if ($options['includeHeaders']) {
            $messages[''] = static::generateHeaders($translations);
        }

        foreach ($translations as $translation) {
            if (!$translation->hasTranslation() || $translation->isDisabled()) {
                continue;
            }

            if ($translation->hasContext()) {
                $originalString = $translation->getContext()."\x04".$translation->getOriginal();
            } else {
                $originalString = $translation->getOriginal();
            }

            $messages[$originalString] = $translation;
        }

        ksort($messages);
        $numEntries = count($messages);
        $originalsTable = '';
        $translationsTable = '';
        $originalsIndex = [];
        $translationsIndex = [];
        $pluralForm = $translations->getPluralForms();
        $pluralSize = is_array($pluralForm) ? ($pluralForm[0] - 1) : null;

        foreach ($messages as $originalString => $translation) {
            if (is_string($translation)) {
                $translationString = $translation;
            } else {
                if ($translation->hasPlural() && $translation->hasPluralTranslations(true)) {
                    $originalString .= "\x00".$translation->getPlural();
                    $translationString = $translation->getTranslation();
                    $translationString .= "\x00".implode("\x00", $translation->getPluralTranslations($pluralSize));
                } else {
                    $translationString = $translation->getTranslation();
                }
            }

            $originalsIndex[] = [
                'relativeOffset' => strlen($originalsTable),
                'length' => strlen($originalString)
            ];
            $originalsTable .= $originalString."\x00";
            $translationsIndex[] = [
                'relativeOffset' => strlen($translationsTable),
                'length' => strlen($translationString)
            ];
            $translationsTable .= $translationString."\x00";
        }

        $originalsIndexOffset = 7 * 4;

        $originalsIndexSize = $numEntries * (4 + 4);

        $translationsIndexOffset = $originalsIndexOffset + $originalsIndexSize;

        $translationsIndexSize = $numEntries * (4 + 4);

        $originalsStringsOffset = $translationsIndexOffset + $translationsIndexSize;

        $translationsStringsOffset = $originalsStringsOffset + strlen($originalsTable);

        $mo = '';

        $mo .= pack('L', 0x950412de);

        $mo .= pack('L', 0);

        $mo .= pack('L', $numEntries);

        $mo .= pack('L', $originalsIndexOffset);

        $mo .= pack('L', $translationsIndexOffset);

        $mo .= pack('L', 0);

        $mo .= pack('L', $translationsIndexOffset + $translationsIndexSize);

        foreach ($originalsIndex as $info) {
            $mo .= pack('L', $info['length']);
            $mo .= pack('L', $originalsStringsOffset + $info['relativeOffset']);
        }

        foreach ($translationsIndex as $info) {
            $mo .= pack('L', $info['length']);
            $mo .= pack('L', $translationsStringsOffset + $info['relativeOffset']);
        }

        $mo .= $originalsTable;

        $mo .= $translationsTable;

        return $mo;
    }
}
