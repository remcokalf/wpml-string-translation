<?php

namespace Gettext;

use Gettext\Generators\PhpArray;

class Translator extends BaseTranslator implements TranslatorInterface
{
    protected $domain;
    protected $dictionary = [];
    protected $plurals = [];

    public function loadTranslations($translations)
    {
        if (is_object($translations) && $translations instanceof Translations) {
            $translations = PhpArray::generate($translations, ['includeHeaders' => false]);
        } elseif (is_string($translations) && is_file($translations)) {
            $translations = include $translations;
        } elseif (!is_array($translations)) {
            throw new \InvalidArgumentException(
                'Invalid Translator: only arrays, files or instance of Translations are allowed'
            );
        }

        $this->addTranslations($translations);

        return $this;
    }

    public function defaultDomain($domain)
    {
        $this->domain = $domain;

        return $this;
    }

    public function gettext($original)
    {
        return $this->dpgettext($this->domain, null, $original);
    }

    public function ngettext($original, $plural, $value)
    {
        return $this->dnpgettext($this->domain, null, $original, $plural, $value);
    }

    public function dngettext($domain, $original, $plural, $value)
    {
        return $this->dnpgettext($domain, null, $original, $plural, $value);
    }

    public function npgettext($context, $original, $plural, $value)
    {
        return $this->dnpgettext($this->domain, $context, $original, $plural, $value);
    }

    public function pgettext($context, $original)
    {
        return $this->dpgettext($this->domain, $context, $original);
    }

    public function dgettext($domain, $original)
    {
        return $this->dpgettext($domain, null, $original);
    }

    public function dpgettext($domain, $context, $original)
    {
        $translation = $this->getTranslation($domain, $context, $original);

        if (isset($translation[0]) && $translation[0] !== '') {
            return $translation[0];
        }

        return $original;
    }

    public function dnpgettext($domain, $context, $original, $plural, $value)
    {
        $translation = $this->getTranslation($domain, $context, $original);
        $key = $this->getPluralIndex($domain, $value, $translation === false);

        if (isset($translation[$key]) && $translation[$key] !== '') {
            return $translation[$key];
        }

        return ($key === 0) ? $original : $plural;
    }

    protected function addTranslations(array $translations)
    {
        $domain = isset($translations['domain']) ? $translations['domain'] : '';

        if ($this->domain === null) {
            $this->domain = $domain;
        }

        if (isset($this->dictionary[$domain])) {
            $this->dictionary[$domain] = array_replace_recursive($this->dictionary[$domain], $translations['messages']);

            return;
        }

        if (!empty($translations['plural-forms'])) {
            list($count, $code) = array_map('trim', explode(';', $translations['plural-forms'], 2));

            $this->plurals[$domain] = [
                'count' => (int) str_replace('nplurals=', '', $count),
                'code' => str_replace('plural=', 'return ', str_replace('n', '$n', $code)).';',
            ];
        }

        $this->dictionary[$domain] = $translations['messages'];
    }

    protected function getTranslation($domain, $context, $original)
    {
        return isset($this->dictionary[$domain][$context][$original])
             ? $this->dictionary[$domain][$context][$original]
             : false;
    }

    protected function getPluralIndex($domain, $n, $fallback)
    {
        if (!isset($this->plurals[$domain]) || $fallback === true) {
            return $n == 1 ? 0 : 1;
        }

        if (!isset($this->plurals[$domain]['function'])) {
            $code = static::fixTerseIfs($this->plurals[$domain]['code']);
            $this->plurals[$domain]['function'] = eval("return function (\$n) { $code };");
        }

        if ($this->plurals[$domain]['count'] <= 2) {
            return call_user_func($this->plurals[$domain]['function'], $n) ? 1 : 0;
        }

        return call_user_func($this->plurals[$domain]['function'], $n);
    }

    private static function fixTerseIfs($code, $inner = false)
    {
        preg_match('/(?P<expression>[^?]+)\?(?P<success>[^:]+):(?P<failure>[^;]+)/', $code, $matches);

        if (!isset($matches[0])) {
            return $code;
        }

        $expression = $matches['expression'];
        $success = $matches['success'];
        $failure = $matches['failure'];

        $failure = static::fixTerseIfs($failure, true);
        $code = $expression.' ? '.$success.' : '.$failure;

        if ($inner) {
            return "($code)";
        }

        return "$code;";
    }
}
