<?php

namespace Gettext;

class Translation
{
    protected $id;
    protected $context;
    protected $original;
    protected $translation = '';
    protected $plural;
    protected $pluralTranslation = [];
    protected $references = [];
    protected $comments = [];
    protected $extractedComments = [];
    protected $flags = [];
    protected $disabled = false;

    public static function generateId($context, $original)
    {
        return "{$context}\004{$original}";
    }

    public static function create($context, $original, $plural = '')
    {
        return new static($context, $original, $plural);
    }

    public function __construct($context, $original, $plural = '')
    {
        $this->context = (string) $context;
        $this->original = (string) $original;

        $this->setPlural($plural);
    }

    public function getClone($context = null, $original = null)
    {
        $new = clone $this;

        if ($context !== null) {
            $new->context = (string) $context;
        }

        if ($original !== null) {
            $new->original = (string) $original;
        }

        return $new;
    }

    public function setId($id)
    {
        $this->id = $id;
    }


    public function getId()
    {
        if ($this->id === null) {
            return static::generateId($this->context, $this->original);
        }
        return $this->id;
    }

    public function is($context, $original = '')
    {
        return (($this->context === $context) && ($this->original === $original)) ? true : false;
    }

    public function setDisabled($disabled)
    {
        $this->disabled = (bool) $disabled;

        return $this;
    }

    public function isDisabled()
    {
        return $this->disabled;
    }

    public function getOriginal()
    {
        return $this->original;
    }

    public function hasOriginal()
    {
        return ($this->original !== '') ? true : false;
    }

    public function setTranslation($translation)
    {
        $this->translation = (string) $translation;

        return $this;
    }

    public function getTranslation()
    {
        return $this->translation;
    }

    public function hasTranslation()
    {
        return ($this->translation !== '') ? true : false;
    }

    public function setPlural($plural)
    {
        $this->plural = (string) $plural;

        return $this;
    }

    public function getPlural()
    {
        return $this->plural;
    }

    public function hasPlural()
    {
        return ($this->plural !== '') ? true : false;
    }

    public function setPluralTranslations(array $plural)
    {
        $this->pluralTranslation = $plural;

        return $this;
    }

    public function getPluralTranslations($size = null)
    {
        if ($size === null) {
            return $this->pluralTranslation;
        }

        $current = count($this->pluralTranslation);

        if ($size > $current) {
            return $this->pluralTranslation + array_fill(0, $size, '');
        }

        if ($size < $current) {
            return array_slice($this->pluralTranslation, 0, $size);
        }

        return $this->pluralTranslation;
    }

    public function hasPluralTranslations($checkContent = false)
    {
        if ($checkContent) {
            return implode('', $this->pluralTranslation) !== '';
        }

        return !empty($this->pluralTranslation);
    }

    public function deletePluralTranslation()
    {
        $this->pluralTranslation = [];

        return $this;
    }

    public function getContext()
    {
        return $this->context;
    }

    public function hasContext()
    {
        return (isset($this->context) && ($this->context !== '')) ? true : false;
    }

    public function addReference($filename, $line = null)
    {
        $key = "{$filename}:{$line}";
        $this->references[$key] = [$filename, $line];

        return $this;
    }

    public function hasReferences()
    {
        return !empty($this->references);
    }

    public function getReferences()
    {
        return array_values($this->references);
    }

    public function deleteReferences()
    {
        $this->references = [];

        return $this;
    }

    public function addComment($comment)
    {
        if (!in_array($comment, $this->comments, true)) {
            $this->comments[] = $comment;
        }

        return $this;
    }

    public function hasComments()
    {
        return isset($this->comments[0]);
    }

    public function getComments()
    {
        return $this->comments;
    }

    public function deleteComments()
    {
        $this->comments = [];

        return $this;
    }

    public function addExtractedComment($comment)
    {
        if (!in_array($comment, $this->extractedComments, true)) {
            $this->extractedComments[] = $comment;
        }

        return $this;
    }

    public function hasExtractedComments()
    {
        return isset($this->extractedComments[0]);
    }

    public function getExtractedComments()
    {
        return $this->extractedComments;
    }

    public function deleteExtractedComments()
    {
        $this->extractedComments = [];

        return $this;
    }

    public function addFlag($flag)
    {
        if (!in_array($flag, $this->flags, true)) {
            $this->flags[] = $flag;
        }

        return $this;
    }

    public function hasFlags()
    {
        return isset($this->flags[0]);
    }

    public function getFlags()
    {
        return $this->flags;
    }

    public function deleteFlags()
    {
        $this->flags = [];

        return $this;
    }

    public function mergeWith(Translation $translation, $options = Merge::DEFAULTS)
    {
        Merge::mergeTranslation($translation, $this, $options);
        Merge::mergeReferences($translation, $this, $options);
        Merge::mergeComments($translation, $this, $options);
        Merge::mergeExtractedComments($translation, $this, $options);
        Merge::mergeFlags($translation, $this, $options);

        return $this;
    }
}
