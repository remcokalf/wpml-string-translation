<?php

namespace Gettext;

use Gettext\Languages\Language;
use BadMethodCallException;
use InvalidArgumentException;
use ArrayObject;

class Translations extends ArrayObject
{
    const HEADER_LANGUAGE = 'Language';
    const HEADER_PLURAL = 'Plural-Forms';
    const HEADER_DOMAIN = 'X-Domain';

    public static $options = [
        'defaultHeaders' => [
            'Project-Id-Version' => '',
            'Report-Msgid-Bugs-To' => '',
            'Last-Translator' => '',
            'Language-Team' => '',
            'MIME-Version' => '1.0',
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Transfer-Encoding' => '8bit',
        ],
        'headersSorting' => false,
        'defaultDateHeaders' => [
            'POT-Creation-Date',
            'PO-Revision-Date',
        ],
    ];

    protected $headers;

    protected $translationClass;

    public function __construct(
        $input = [],
        $flags = 0,
        $iterator_class = 'ArrayIterator',
        $translationClass = 'Gettext\Translation'
    ) {
        $this->headers = static::$options['defaultHeaders'];

        foreach (static::$options['defaultDateHeaders'] as $header) {
            $this->headers[$header] = date('c');
        }

        $this->headers[self::HEADER_LANGUAGE] = '';

        $this->translationClass = $translationClass;

        parent::__construct($input, $flags, $iterator_class);
    }

    public static function __callStatic($name, $arguments)
    {
        if (!preg_match('/^from(\w+)(File|String)$/i', $name, $matches)) {
            throw new BadMethodCallException("The method $name does not exists");
        }

        return call_user_func_array([new static(), 'add'.ucfirst($name)], $arguments);
    }

    public function __call($name, $arguments)
    {
        if (!preg_match('/^(addFrom|to)(\w+)(File|String)$/i', $name, $matches)) {
            throw new BadMethodCallException("The method $name does not exists");
        }

        if ($matches[1] === 'addFrom') {
            $extractor = 'Gettext\\Extractors\\'.$matches[2].'::from'.$matches[3];
            $source = array_shift($arguments);
            $options = array_shift($arguments) ?: [];

            call_user_func($extractor, $source, $this, $options);

            return $this;
        }

        $generator = 'Gettext\\Generators\\'.$matches[2].'::to'.$matches[3];

        array_unshift($arguments, $this);

        return call_user_func_array($generator, $arguments);
    }

    public function __clone()
    {
        $array = [];

        foreach ($this as $key => $translation) {
            $array[$key] = clone $translation;
        }

        $this->exchangeArray($array);
    }

    #[\ReturnTypeWillChange]
    public function offsetSet($index, $value)
    {
        if (!($value instanceof Translation)) {
            throw new InvalidArgumentException(
                'Only instances of Gettext\\Translation must be added to a Gettext\\Translations'
            );
        }

        $id = $value->getId();

        if ($this->offsetExists($id)) {
            $this[$id]->mergeWith($value);

            return $this[$id];
        }

        parent::offsetSet($id, $value);

        return $value;
    }

    public function setPluralForms($count, $rule)
    {
        if (preg_match('/[a-z]/i', str_replace('n', '', $rule))) {
            throw new \InvalidArgumentException('Invalid Plural form: ' . $rule);
        }
        $this->setHeader(self::HEADER_PLURAL, "nplurals={$count}; plural={$rule};");

        return $this;
    }

    public function getPluralForms()
    {
        $header = $this->getHeader(self::HEADER_PLURAL);

        if (!empty($header)
            && preg_match('/^nplurals\s*=\s*(\d+)\s*;\s*plural\s*=\s*([^;]+)\s*;$/', $header, $matches)
        ) {
            return [intval($matches[1]), $matches[2]];
        }
    }

    public function setHeader($name, $value)
    {
        $name = trim($name);
        $this->headers[$name] = trim(isset($value) ? $value : '');

        return $this;
    }

    public function getHeader($name)
    {
        return isset($this->headers[$name]) ? $this->headers[$name] : null;
    }

    public function getHeaders()
    {
        if (static::$options['headersSorting']) {
            ksort($this->headers);
        }

        return $this->headers;
    }

    public function deleteHeaders()
    {
        $this->headers = [];

        return $this;
    }

    public function deleteHeader($name)
    {
        unset($this->headers[$name]);

        return $this;
    }

    public function getLanguage()
    {
        return $this->getHeader(self::HEADER_LANGUAGE);
    }

    public function setLanguage($language)
    {
        $this->setHeader(self::HEADER_LANGUAGE, trim($language));

        if (($info = Language::getById($language))) {
            return $this->setPluralForms(count($info->categories), $info->formula);
        }

        throw new InvalidArgumentException(sprintf('The language "%s" is not valid', $language));
    }

    public function hasLanguage()
    {
        $language = $this->getLanguage();

        return (is_string($language) && ($language !== '')) ? true : false;
    }

    public function setDomain($domain)
    {
        $this->setHeader(self::HEADER_DOMAIN, trim($domain));

        return $this;
    }

    public function getDomain()
    {
        return $this->getHeader(self::HEADER_DOMAIN);
    }

    public function hasDomain()
    {
        $domain = $this->getDomain();

        return (is_string($domain) && ($domain !== '')) ? true : false;
    }

    public function find($context, $original = '')
    {
        if ($context instanceof Translation) {
            $id = $context->getId();
        } else {
            $id = Translation::generateId($context, $original);
        }

        return $this->offsetExists($id) ? $this[$id] : false;
    }

    public function countTranslated()
    {
        $c = 0;
        foreach ($this as $v) {
            if ($v->hasTranslation()) {
                $c++;
            }
        }
        return $c;
    }

    public function insert($context, $original, $plural = '')
    {
        return $this->offsetSet(null, $this->createNewTranslation($context, $original, $plural));
    }

    public function mergeWith(Translations $translations, $options = Merge::DEFAULTS)
    {
        Merge::mergeHeaders($translations, $this, $options);
        Merge::mergeTranslations($translations, $this, $options);

        return $this;
    }

    public function createNewTranslation($context, $original, $plural = '')
    {
        $class = $this->translationClass;
        return $class::create($context, $original, $plural);
    }
}
