<?php

namespace Gettext\Languages;

use Exception;

class CldrData
{
    const OTHER_CATEGORY = 'other';

    public static $categories = array('zero', 'one', 'two', 'few', 'many', self::OTHER_CATEGORY);

    private static $data;

    private static $plurals;

    public static function getLanguageNames()
    {
        return self::getData('languages');
    }

    public static function getTerritoryNames()
    {
        return self::getData('territories');
    }

    public static function getScriptNames($standAlone)
    {
        return self::getData($standAlone ? 'standAloneScripts' : 'scripts');
    }

    public static function getPlurals()
    {
        return self::getData('plurals');
    }

    public static function getSupersededLanguages()
    {
        return self::getData('supersededLanguages');
    }

    public static function getLanguageInfo($id)
    {
        $result = null;
        $matches = array();
        if (preg_match('/^([a-z]{2,3})(?:[_\-]([a-z]{4}))?(?:[_\-]([a-z]{2}|[0-9]{3}))?(?:$|-)/i', $id, $matches)) {
            $languageId = strtolower($matches[1]);
            $scriptId = (isset($matches[2]) && ($matches[2] !== '')) ? ucfirst(strtolower($matches[2])) : null;
            $territoryId = (isset($matches[3]) && ($matches[3] !== '')) ? strtoupper($matches[3]) : null;
            $normalizedId = $languageId;
            if (isset($scriptId)) {
                $normalizedId .= '_' . $scriptId;
            }
            if (isset($territoryId)) {
                $normalizedId .= '_' . $territoryId;
            }
            $variants = array();
            $variantsWithScript = array();
            $variantsWithTerritory = array();
            if (isset($scriptId) && isset($territoryId)) {
                $variantsWithTerritory[] = $variantsWithScript[] = $variants[] = "{$languageId}_{$scriptId}_{$territoryId}";
            }
            if (isset($scriptId)) {
                $variantsWithScript[] = $variants[] = "{$languageId}_{$scriptId}";
            }
            if (isset($territoryId)) {
                $variantsWithTerritory[] = $variants[] = "{$languageId}_{$territoryId}";
            }
            $variants[] = $languageId;
            $allGood = true;
            $scriptName = null;
            $scriptStandAloneName = null;
            if (isset($scriptId)) {
                $scriptNames = self::getScriptNames(false);
                if (isset($scriptNames[$scriptId])) {
                    $scriptName = $scriptNames[$scriptId];
                    $scriptStandAloneNames = self::getScriptNames(true);
                    $scriptStandAloneName = $scriptStandAloneNames[$scriptId];
                } else {
                    $allGood = false;
                }
            }
            $territoryName = null;
            if (isset($territoryId)) {
                $territoryNames = self::getTerritoryNames();
                if (isset($territoryNames[$territoryId])) {
                    if ($territoryId !== '001') {
                        $territoryName = $territoryNames[$territoryId];
                    }
                } else {
                    $allGood = false;
                }
            }
            $languageName = null;
            $languageNames = self::getLanguageNames();
            foreach ($variants as $variant) {
                if (isset($languageNames[$variant])) {
                    $languageName = $languageNames[$variant];
                    if (isset($scriptName) && (!in_array($variant, $variantsWithScript))) {
                        $languageName = $scriptName . ' ' . $languageName;
                    }
                    if (isset($territoryName) && (!in_array($variant, $variantsWithTerritory))) {
                        $languageName .= ' (' . $territoryNames[$territoryId] . ')';
                    }
                    break;
                }
            }
            if (!isset($languageName)) {
                $allGood = false;
            }
            $baseLanguage = null;
            if (isset($scriptId) || isset($territoryId)) {
                if (isset($languageNames[$languageId]) && ($languageNames[$languageId] !== $languageName)) {
                    $baseLanguage = $languageNames[$languageId];
                }
            }
            $plural = null;
            $plurals = self::getPlurals();
            foreach ($variants as $variant) {
                if (isset($plurals[$variant])) {
                    $plural = $plurals[$variant];
                    break;
                }
            }
            if (!isset($plural)) {
                $allGood = false;
            }
            $supersededBy = null;
            $supersededBys = self::getSupersededLanguages();
            foreach ($variants as $variant) {
                if (isset($supersededBys[$variant])) {
                    $supersededBy = $supersededBys[$variant];
                    break;
                }
            }
            if ($allGood) {
                $result = array();
                $result['id'] = $normalizedId;
                $result['name'] = $languageName;
                if (isset($supersededBy)) {
                    $result['supersededBy'] = $supersededBy;
                }
                if (isset($scriptStandAloneName)) {
                    $result['script'] = $scriptStandAloneName;
                }
                if (isset($territoryName)) {
                    $result['territory'] = $territoryName;
                }
                if (isset($baseLanguage)) {
                    $result['baseLanguage'] = $baseLanguage;
                }
                $result['categories'] = $plural;
            }
        }

        return $result;
    }

    private static function getData($key)
    {
        if (!isset(self::$data)) {
            $fixKeys = function ($list, &$standAlone = null) {
                $result = array();
                $standAlone = array();
                $match = null;
                foreach ($list as $key => $value) {
                    $variant = '';
                    if (preg_match('/^(.+)-alt-(short|variant|stand-alone|long|menu)$/', $key, $match)) {
                        $key = $match[1];
                        $variant = $match[2];
                    }
                    $key = str_replace('-', '_', $key);
                    switch ($key) {
                        case 'root':
                        case 'und':
                        case 'zxx':
                        case 'ZZ':
                        case 'Zinh':
                        case 'Zmth':
                        case 'Zsym':
                        case 'Zxxx':
                        case 'Zyyy':
                        case 'Zzzz':
                            break;
                        default:
                            switch ($variant) {
                                case 'stand-alone':
                                    $standAlone[$key] = $value;
                                    break;
                                case '':
                                    $result[$key] = $value;
                                    break;
                            }
                            break;
                    }
                }

                return $result;
            };
            $data = array();
            $json = json_decode(file_get_contents(__DIR__ . '/cldr-data/main/en-US/languages.json'), true);
            $data['languages'] = $fixKeys($json['main']['en-US']['localeDisplayNames']['languages']);
            $json = json_decode(file_get_contents(__DIR__ . '/cldr-data/main/en-US/territories.json'), true);
            $data['territories'] = $fixKeys($json['main']['en-US']['localeDisplayNames']['territories']);
            $json = json_decode(file_get_contents(__DIR__ . '/cldr-data/supplemental/plurals.json'), true);
            $data['plurals'] = $fixKeys($json['supplemental']['plurals-type-cardinal']);
            $json = json_decode(file_get_contents(__DIR__ . '/cldr-data/main/en-US/scripts.json'), true);
            $data['scripts'] = $fixKeys($json['main']['en-US']['localeDisplayNames']['scripts'], $data['standAloneScripts']);
            $data['standAloneScripts'] = array_merge($data['scripts'], $data['standAloneScripts']);
            $data['scripts'] = array_merge($data['standAloneScripts'], $data['scripts']);
            $data['supersededLanguages'] = array();
            $m = null;
            foreach (array_keys(array_diff_key($data['languages'], $data['plurals'])) as $missingPlural) {
                if (preg_match('/^([a-z]{2,3})_/', $missingPlural, $m)) {
                    if (!isset($data['plurals'][$m[1]])) {
                        unset($data['languages'][$missingPlural]);
                    }
                } else {
                    unset($data['languages'][$missingPlural]);
                }
            }
            $formerCodes = array(
                'jw' => 'jv',
                'mo' => 'ro_MD',
            );
            $knownMissingLanguages = array(
                'guw' => 'Gun',
                'hnj' => 'Hmong Njua',
                'lld' => 'Dolomitic Ladin',
                'nah' => 'Nahuatl',
                'smi' => 'Sami',
            );
            foreach (array_keys(array_diff_key($data['plurals'], $data['languages'])) as $missingLanguage) {
                if (isset($formerCodes[$missingLanguage]) && isset($data['languages'][$formerCodes[$missingLanguage]])) {
                    $data['languages'][$missingLanguage] = $data['languages'][$formerCodes[$missingLanguage]];
                    $data['supersededLanguages'][$missingLanguage] = $formerCodes[$missingLanguage];
                } else {
                    if (isset($knownMissingLanguages[$missingLanguage])) {
                        $data['languages'][$missingLanguage] = $knownMissingLanguages[$missingLanguage];
                    } else {
                        throw new Exception("We have the plural rule for the language '{$missingLanguage}' but we don't have its language name");
                    }
                }
            }
            ksort($data['languages'], SORT_STRING);
            ksort($data['territories'], SORT_STRING);
            ksort($data['plurals'], SORT_STRING);
            ksort($data['scripts'], SORT_STRING);
            ksort($data['standAloneScripts'], SORT_STRING);
            ksort($data['supersededLanguages'], SORT_STRING);
            self::$data = $data;
        }
        if (!isset(self::$data[$key])) {
            throw new Exception("Invalid CLDR data key: '{$key}'");
        }

        return self::$data[$key];
    }
}
