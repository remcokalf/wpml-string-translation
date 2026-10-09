<?php

namespace Gettext\Utils;

use Exception;
use Gettext\Translations;

abstract class FunctionsScanner
{
    abstract public function getFunctions(array $constants = []);

    public function saveGettextFunctions($translations, array $options)
    {
        $translations = is_array($translations) ? $translations : [$translations];

        $translationByDomain = array_reduce($translations, function ($carry, Translations $translations) {
            $carry[$translations->getDomain()] = $translations;
            return $carry;
        }, []);

        $functions = $options['functions'];
        $file = $options['file'];

        $commentsCache = [];

        foreach ($this->getFunctions($options['constants']) as $function) {
            list($name, $line, $args) = $function;

            if (isset($options['lineOffset'])) {
                $line += $options['lineOffset'];
            }

            if (!isset($functions[$name])) {
                continue;
            }

            $deconstructed = $this->deconstructArgs($functions[$name], $args);

            if (!$deconstructed) {
                continue;
            }

            list($domain, $context, $original, $plural) = $deconstructed;

            if ((string)$original === '') {
                continue;
            }

            $isDefaultDomain = $domain === null;

            $domainTranslations = isset($translationByDomain[$domain]) ? $translationByDomain[$domain] : false;

            if (!empty($options['domainOnly']) && $isDefaultDomain) {
                continue;
            }

            if (!$domainTranslations) {
                continue;
            }

            $translation = $domainTranslations->insert($context, $original, $plural);
            $translation->addReference($file, $line);

            if (isset($function[3])) {
                foreach ($function[3] as $extractedComment) {
                    if (in_array($extractedComment, $commentsCache, true)) {
                        continue;
                    }
                    $translation->addExtractedComment($extractedComment->getComment());
                    $commentsCache[] = $extractedComment;
                }
            }
        }
    }

    protected function deconstructArgs($function, $args)
    {
        $domain = null;
        $context = null;
        $original = null;
        $plural = null;

        switch ($function) {
            case 'noop':
            case 'gettext':
                if (!isset($args[0])) {
                    return null;
                }

                $original = $args[0];
                break;
            case 'ngettext':
                if (!isset($args[1])) {
                    return null;
                }

                list($original, $plural) = $args;
                break;
            case 'pgettext':
                if (!isset($args[1])) {
                    return null;
                }

                list($context, $original) = $args;
                break;
            case 'dgettext':
                if (!isset($args[1])) {
                    return null;
                }

                list($domain, $original) = $args;
                break;
            case 'dpgettext':
                if (!isset($args[2])) {
                    return null;
                }

                list($domain, $context, $original) = $args;
                break;
            case 'npgettext':
                if (!isset($args[2])) {
                    return null;
                }

                list($context, $original, $plural) = $args;
                break;
            case 'dnpgettext':
                if (!isset($args[3])) {
                    return null;
                }

                list($domain, $context, $original, $plural) = $args;
                break;
            case 'dngettext':
                if (!isset($args[2])) {
                    return null;
                }

                list($domain, $original, $plural) = $args;
                break;
            default:
                throw new Exception(sprintf('Not valid function %s', $function));
        }

        return [$domain, $context, $original, $plural];
    }
}
