<?php

namespace Gettext\Extractors;

use Exception;
use Gettext\Translations;
use Gettext\Utils\StringReader;

class Mo extends Extractor implements ExtractorInterface
{
    const MAGIC1 = -1794895138;
    const MAGIC2 = -569244523;
    const MAGIC3 = 2500072158;

    protected static $stringReaderClass = 'Gettext\Utils\StringReader';

    public static function fromString($string, Translations $translations, array $options = [])
    {
        $stream = new static::$stringReaderClass($string);
        $magic = static::readInt($stream, 'V');

        if (($magic === static::MAGIC1) || ($magic === static::MAGIC3)) {
            $byteOrder = 'V';
        } elseif ($magic === (static::MAGIC2 & 0xFFFFFFFF)) {
            $byteOrder = 'N';
        } else {
            throw new Exception('Not MO file');
        }

        static::readInt($stream, $byteOrder);

        $total = static::readInt($stream, $byteOrder);
        $originals = static::readInt($stream, $byteOrder);
        $tran = static::readInt($stream, $byteOrder);

        $stream->seekto($originals);
        $table_originals = static::readIntArray($stream, $byteOrder, $total * 2);

        $stream->seekto($tran);
        $table_translations = static::readIntArray($stream, $byteOrder, $total * 2);

        for ($i = 0; $i < $total; ++$i) {
            $next = $i * 2;

            $stream->seekto($table_originals[$next + 2]);
            $original = $stream->read($table_originals[$next + 1]);

            $stream->seekto($table_translations[$next + 2]);
            $translated = $stream->read($table_translations[$next + 1]);

            if ($original === '') {
                foreach (explode("\n", $translated) as $headerLine) {
                    if ($headerLine === '') {
                        continue;
                    }

                    $headerChunks = preg_split('/:\s*/', $headerLine, 2);
                    $translations->setHeader($headerChunks[0], isset($headerChunks[1]) ? $headerChunks[1] : '');
                }

                continue;
            }

            $chunks = explode("\x04", $original, 2);

            if (isset($chunks[1])) {
                $context = $chunks[0];
                $original = $chunks[1];
            } else {
                $context = '';
            }

            $chunks = explode("\x00", $original, 2);

            if (isset($chunks[1])) {
                $original = $chunks[0];
                $plural = $chunks[1];
            } else {
                $plural = '';
            }

            $translation = $translations->insert($context, $original, $plural);

            if ($translated === '') {
                continue;
            }

            if ($plural === '') {
                $translation->setTranslation($translated);
                continue;
            }

            $v = explode("\x00", $translated);
            $translation->setTranslation(array_shift($v));
            $translation->setPluralTranslations($v);
        }
    }

    protected static function readInt(StringReader $stream, $byteOrder)
    {
        if (($read = $stream->read(4)) === false) {
            return false;
        }

        $read = unpack($byteOrder, $read);

        return array_shift($read);
    }

    protected static function readIntArray(StringReader $stream, $byteOrder, $count)
    {
        return unpack($byteOrder.$count, $stream->read(4 * $count));
    }
}
