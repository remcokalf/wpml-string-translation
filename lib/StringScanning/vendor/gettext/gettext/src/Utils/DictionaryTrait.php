<?php

namespace Gettext\Utils;

use Gettext\Translations;

trait DictionaryTrait
{
    use HeadersGeneratorTrait;
    use HeadersExtractorTrait;

    protected static function toArray(Translations $translations, $includeHeaders)
    {
        $messages = [];

        if ($includeHeaders) {
            $messages[''] = static::generateHeaders($translations);
        }

        foreach ($translations as $translation) {
            if ($translation->isDisabled()) {
                continue;
            }

            $messages[$translation->getOriginal()] = $translation->getTranslation();
        }

        return $messages;
    }

    protected static function fromArray(array $messages, Translations $translations)
    {
        foreach ($messages as $original => $translation) {
            if ($original === '') {
                static::extractHeaders($translation, $translations);
                continue;
            }

            $translations->insert(null, $original)->setTranslation($translation);
        }
    }
}
