<?php

namespace Gettext\Extractors;

use DOMAttr;
use DOMDocument;
use DOMElement;
use DOMNode;
use Exception;
use Gettext\Translations;
use Gettext\Utils\FunctionsScanner;

class VueJs extends Extractor implements ExtractorInterface, ExtractorMultiInterface
{
    public static $options = [
        'constants' => [],

        'functions' => [
            'gettext' => 'gettext',
            '__' => 'gettext',
            'ngettext' => 'ngettext',
            'n__' => 'ngettext',
            'pgettext' => 'pgettext',
            'p__' => 'pgettext',
            'dgettext' => 'dgettext',
            'd__' => 'dgettext',
            'dngettext' => 'dngettext',
            'dn__' => 'dngettext',
            'dpgettext' => 'dpgettext',
            'dp__' => 'dpgettext',
            'npgettext' => 'npgettext',
            'np__' => 'npgettext',
            'dnpgettext' => 'dnpgettext',
            'dnp__' => 'dnpgettext',
            'noop' => 'noop',
            'noop__' => 'noop',
        ],
    ];

    protected static $functionsScannerClass = 'Gettext\Utils\JsFunctionsScanner';

    public static function fromFileMultiple($file, array $translations, array $options = [])
    {
        foreach (static::getFiles($file) as $file) {
            $options['file'] = $file;
            static::fromStringMultiple(static::readFile($file), $translations, $options);
        }
    }

    public static function fromString($string, Translations $translations, array $options = [])
    {
        static::fromStringMultiple($string, [$translations], $options);
    }

    public static function fromStringMultiple($string, array $translations, array $options = [])
    {
        $options += static::$options;
        $options += [
            'attributePrefixes' => [
                ':',
                'v-bind:',
                'v-on:',
                'v-text',
            ],
            'tagNames' => [
                'translate',
            ],
            'tagAttributes' => [
                'v-translate',
            ],
            'commentAttributes' => [
                'translate-comment',
            ],
            'contextAttributes' => [
                'translate-context',
            ],
            'pluralAttributes' => [
                'translate-plural',
            ],
        ];

        $string = str_replace('<template>', '<template>.', $string);
        $string = str_replace('</template>', '</template>.', $string);

        $string = str_replace(["\r\n", "\n\r", "\r"], "\n", $string);

        $dom = static::convertHtmlToDom($string);

        $script = static::extractScriptTag($string);

        if ($script) {
            $scriptLineNumber = $dom->getElementsByTagName('script')->item(0)->getLineNo();
            static::getScriptTranslationsFromString(
                $script,
                $translations,
                $options,
                $scriptLineNumber - 1
            );
        }

        $template = $dom->getElementsByTagName('template')->item(0);
        if ($template) {
            static::getTemplateTranslations(
                $template,
                $translations,
                $options,
                $template->getLineNo() - 1
            );
        }
    }

    protected static function extractScriptTag($string)
    {
        if (preg_match('#<\s*?script\b[^>]*>(.*?)</script\b[^>]*>#s', $string, $matches)) {
            return $matches[1];
        }

        return '';
    }

    protected static function convertHtmlToDom($html)
    {
        $dom = new DOMDocument;

        libxml_use_internal_errors(true);

        $dom->loadHTML('<?xml encoding="utf-8"?>' . $html);

        libxml_clear_errors();

        return $dom;
    }

    protected static function getScriptTranslationsFromString(
        $scriptContents,
        $translations,
        array $options = [],
        $lineOffset = 0
    ) {
        $functions = new static::$functionsScannerClass($scriptContents);
        $options['lineOffset'] = $lineOffset;
        $functions->saveGettextFunctions($translations, $options);
    }

    protected static function getTemplateTranslations(
        DOMNode $dom,
        $translations,
        array $options,
        $lineOffset = 0
    ) {
        $fakeAttributeJs = static::getTemplateAttributeFakeJs($options, $dom);

        static::getScriptTranslationsFromString($fakeAttributeJs, $translations, $options, $lineOffset);

        $fakeTemplateJs = static::getTemplateFakeJs($dom);
        static::getScriptTranslationsFromString($fakeTemplateJs, $translations, $options, $lineOffset);

        static::getTagTranslations($options, $dom, $translations);
    }

    protected static function getTagTranslations(array $options, DOMNode $dom, $translations)
    {
        $translations = is_array($translations) ? reset($translations) : $translations;

        $children = $dom->childNodes;
        for ($i = 0; $i < $children->length; $i++) {
            $node = $children->item($i);

            if (!($node instanceof DOMElement)) {
                continue;
            }

            $translatable = false;

            if (in_array($node->tagName, $options['tagNames'], true)) {
                $translatable = true;
            }

            $attrList = $node->attributes;
            $context = null;
            $plural = "";
            $comment = null;

            for ($j = 0; $j < $attrList->length; $j++) {
                $domAttr = $attrList->item($j);
                if (in_array($domAttr->name, $options['tagAttributes'])) {
                    $translatable = true;
                }
                if (in_array($domAttr->name, $options['contextAttributes'])) {
                    $context = $domAttr->value;
                }
                if (in_array($domAttr->name, $options['pluralAttributes'])) {
                    $plural = $domAttr->value;
                }
                if (in_array($domAttr->name, $options['commentAttributes'])) {
                    $comment = $domAttr->value;
                }
            }

            if ($translatable) {
                $translation = $translations->insert($context, trim($node->textContent), $plural);
                $translation->addReference($options['file'], $node->getLineNo());
                if ($comment) {
                    $translation->addExtractedComment($comment);
                }
            }

            if ($node->hasChildNodes()) {
                static::getTagTranslations($options, $node, $translations);
            }
        }
    }

    protected static function getTemplateAttributeFakeJs(array $options, DOMNode $dom)
    {
        $expressionsByLine = static::getVueAttributeExpressions($options['attributePrefixes'], $dom);

        if (empty($expressionsByLine)) {
            return '';
        }

        $maxLines = max(array_keys($expressionsByLine));
        $fakeJs = '';

        for ($line = 1; $line <= $maxLines; $line++) {
            if (isset($expressionsByLine[$line])) {
                $fakeJs .= implode("; ", $expressionsByLine[$line]);
            }
            $fakeJs .= "\n";
        }

        return $fakeJs;
    }

    protected static function getVueAttributeExpressions(
        array $attributePrefixes,
        DOMNode $dom,
        array &$expressionByLine = []
    ) {
        $children = $dom->childNodes;

        for ($i = 0; $i < $children->length; $i++) {
            $node = $children->item($i);

            if (!($node instanceof DOMElement)) {
                continue;
            }
            $attrList = $node->attributes;

            for ($j = 0; $j < $attrList->length; $j++) {
                $domAttr = $attrList->item($j);

                if (static::isAttributeMatching($domAttr->name, $attributePrefixes)) {
                    $line = $domAttr->getLineNo();
                    $expressionByLine += [$line => []];
                    $expressionByLine[$line][] = $domAttr->value;
                }
            }

            if ($node->hasChildNodes()) {
                $expressionByLine = static::getVueAttributeExpressions($attributePrefixes, $node, $expressionByLine);
            }
        }

        return $expressionByLine;
    }

    protected static function isAttributeMatching($attributeName, $attributePrefixes)
    {
        foreach ($attributePrefixes as $prefix) {
            if (strpos($attributeName, $prefix) === 0) {
                return true;
            }
        }
        return false;
    }

    protected static function getTemplateFakeJs(DOMNode $dom)
    {
        $fakeJs = '';
        $lines = explode("\n", $dom->textContent);

        foreach ($lines as $line) {
            $expressionMatched = static::parseOneTemplateLine($line);

            $fakeJs .= implode("; ", $expressionMatched) . "\n";
        }

        return $fakeJs;
    }

    protected static function parseOneTemplateLine($line)
    {
        $line = trim($line);

        if (!$line) {
            return [];
        }

        $regex = '#\{\{(.*?)\}\}#';

        preg_match_all($regex, $line, $matches);

        $matched = array_map(function ($v) {
            return trim($v, '\'"{}');
        }, $matches[1]);

        return $matched;
    }
}
