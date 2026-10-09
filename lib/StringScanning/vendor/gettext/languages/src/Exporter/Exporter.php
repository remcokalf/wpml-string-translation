<?php

namespace Gettext\Languages\Exporter;

use Exception;

abstract class Exporter
{
    private static $exporters;

    final public static function getExporters($onlyForPublicUse = false)
    {
        if (!isset(self::$exporters)) {
            $exporters = array();
            $m = null;
            foreach (scandir(__DIR__) as $f) {
                if (preg_match('/^(\w+)\.php$/', $f, $m)) {
                    if ($f !== basename(__FILE__)) {
                        $exporters[strtolower($m[1])] = $m[1];
                    }
                }
            }
            self::$exporters = $exporters;
        }
        if ($onlyForPublicUse) {
            $result = array();
            foreach (self::$exporters as $handle => $class) {
                if (call_user_func(self::getExporterClassName($handle) . '::isForPublicUse') === true) {
                    $result[$handle] = $class;
                }
            }
        } else {
            $result = self::$exporters;
        }

        return $result;
    }

    final public static function getExporterDescription($exporterHandle)
    {
        $exporters = self::getExporters();
        if (!isset($exporters[$exporterHandle])) {
            throw new Exception("Invalid exporter handle: '{$exporterHandle}'");
        }

        return call_user_func(self::getExporterClassName($exporterHandle) . '::getDescription');
    }

    final public static function getExporterClassName($exporterHandle)
    {
        return __NAMESPACE__ . '\\' . ucfirst(strtolower($exporterHandle));
    }

    final public static function toString($languages, $options = null)
    {
        if (!isset($options) || !is_array($options)) {
            $options = array();
        }
        if (isset($options['us-ascii']) && $options['us-ascii']) {
            $asciiList = array();
            foreach ($languages as $language) {
                $asciiList[] = $language->getUSAsciiClone();
            }
            $languages = $asciiList;
        }

        return static::toStringDoWithOptions($languages, $options);
    }

    final public static function toFile($languages, $filename, $options = null)
    {
        $data = self::toString($languages, $options);
        if (@file_put_contents($filename, $data) === false) {
            throw new Exception("Error writing data to '{$filename}'");
        }
    }

    public static function isForPublicUse()
    {
        return true;
    }

    public static function supportsFormulasWithAndWithoutParenthesis()
    {
        return false;
    }

    public static function getDescription()
    {
        throw new Exception(get_called_class() . ' does not implement the method ' . __FUNCTION__);
    }

    protected static function toStringDoWithOptions($languages, array $options)
    {
        if (method_exists(get_called_class(), 'toStringDo')) {
            return static::toStringDo($languages);
        }
        throw new Exception(get_called_class() . ' does not implement the method ' . __FUNCTION__);
    }
}
