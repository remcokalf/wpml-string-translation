<?php

namespace Gettext;

interface TranslatorInterface
{
    public function register();

    public function noop($original);

    public function gettext($original);

    public function ngettext($original, $plural, $value);

    public function dngettext($domain, $original, $plural, $value);

    public function npgettext($context, $original, $plural, $value);

    public function pgettext($context, $original);

    public function dgettext($domain, $original);

    public function dpgettext($domain, $context, $original);

    public function dnpgettext($domain, $context, $original, $plural, $value);
}
