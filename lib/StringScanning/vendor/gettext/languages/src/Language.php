<?php

namespace Gettext\Languages;

use Exception;

class Language
{
    public $id;

    public $name;

    public $supersededBy;

    public $script;

    public $territory;

    public $baseLanguage;

    public $categories;

    public $formula;

    private function __construct($info)
    {
        $this->id = $info['id'];
        $this->name = $info['name'];
        $this->supersededBy = isset($info['supersededBy']) ? $info['supersededBy'] : null;
        $this->script = isset($info['script']) ? $info['script'] : null;
        $this->territory = isset($info['territory']) ? $info['territory'] : null;
        $this->baseLanguage = isset($info['baseLanguage']) ? $info['baseLanguage'] : null;
        $this->categories = array();
        foreach ($info['categories'] as $cldrCategoryId => $cldrFormulaAndExamples) {
            $category = new Category($cldrCategoryId, $cldrFormulaAndExamples);
            foreach ($this->categories as $c) {
                if ($category->id === $c->id) {
                    throw new Exception("The category '{$category->id}' is specified more than once");
                }
            }
            $this->categories[] = $category;
        }
        if (empty($this->categories)) {
            throw new Exception("The language '{$info['id']}' does not have any plural category");
        }
        usort($this->categories, function (Category $category1, Category $category2) {
            return array_search($category1->id, CldrData::$categories) - array_search($category2->id, CldrData::$categories);
        });
        if ($this->categories[count($this->categories) - 1]->id !== CldrData::OTHER_CATEGORY) {
            throw new Exception("The language '{$info['id']}' does not have the '" . CldrData::OTHER_CATEGORY . "' plural category");
        }
        $this->checkAlwaysTrueCategories();
        $this->checkAlwaysFalseCategories();
        $this->checkAllCategoriesWithExamples();
        $this->formula = $this->buildFormula();
    }

    public static function getAll()
    {
        $result = array();
        foreach (array_keys(CldrData::getLanguageNames()) as $cldrLanguageId) {
            $result[] = new self(CldrData::getLanguageInfo($cldrLanguageId));
        }

        return $result;
    }

    public static function getById($id)
    {
        $result = null;
        $info = CldrData::getLanguageInfo($id);
        if (isset($info)) {
            $result = new self($info);
        }

        return $result;
    }

    public function getUSAsciiClone()
    {
        $clone = clone $this;
        self::asciifier($clone->name);
        self::asciifier($clone->formula);
        $clone->categories = array();
        foreach ($this->categories as $category) {
            $categoryClone = clone $category;
            self::asciifier($categoryClone->examples);
            $clone->categories[] = $categoryClone;
        }

        return $clone;
    }

    public function buildFormula($withoutParenthesis = false)
    {
        $numCategories = count($this->categories);
        switch ($numCategories) {
            case 1:
                return '0';
            case 2:
                return self::reduceFormula(self::reverseFormula($this->categories[0]->formula));
            default:
                $formula = (string) ($numCategories - 1);
                for ($i = $numCategories - 2; $i >= 0; $i--) {
                    $f = self::reduceFormula($this->categories[$i]->formula);
                    if (!$withoutParenthesis && !preg_match('/^\([^()]+\)$/', $f)) {
                        $f = "({$f})";
                    }
                    $formula = "{$f} ? {$i} : {$formula}";
                    if (!$withoutParenthesis && $i > 0) {
                        $formula = "({$formula})";
                    }
                }

                return $formula;
        }
    }

    private function checkAlwaysTrueCategories()
    {
        $alwaysTrueCategory = null;
        foreach ($this->categories as $category) {
            if ($category->formula === true) {
                if (!isset($category->examples)) {
                    throw new Exception("The category '{$category->id}' should always occur, but it does not have examples (so for CLDR it will never occur for integers!)");
                }
                $alwaysTrueCategory = $category;
                break;
            }
        }
        if (isset($alwaysTrueCategory)) {
            foreach ($this->categories as $category) {
                if (($category !== $alwaysTrueCategory) && isset($category->examples)) {
                    throw new Exception("The category '{$category->id}' should never occur, but it has some examples (so for CLDR it will occur!)");
                }
            }
            $alwaysTrueCategory->id = CldrData::OTHER_CATEGORY;
            $alwaysTrueCategory->formula = null;
            $this->categories = array($alwaysTrueCategory);
        }
    }

    private function checkAlwaysFalseCategories()
    {
        $filtered = array();
        foreach ($this->categories as $category) {
            if ($category->formula === false) {
                if (isset($category->examples)) {
                    throw new Exception("The category '{$category->id}' should never occur, but it has examples (so for CLDR it may occur!)");
                }
            } else {
                $filtered[] = $category;
            }
        }
        $this->categories = $filtered;
    }

    private function checkAllCategoriesWithExamples()
    {
        $allCategoriesIds = array();
        $goodCategories = array();
        $badCategories = array();
        $badCategoriesIds = array();
        foreach ($this->categories as $category) {
            $allCategoriesIds[] = $category->id;
            if (isset($category->examples)) {
                $goodCategories[] = $category;
            } else {
                $badCategories[] = $category;
                $badCategoriesIds[] = $category->id;
            }
        }
        if (empty($badCategories)) {
            return;
        }
        $removeCategoriesWithoutExamples = false;
        switch (implode(',', $badCategoriesIds) . '@' . implode(',', $allCategoriesIds)) {
            case CldrData::OTHER_CATEGORY . '@one,few,many,' . CldrData::OTHER_CATEGORY:
                switch ($this->buildFormula()) {
                    case '(n % 10 == 1 && n % 100 != 11) ? 0 : ((n % 10 >= 2 && n % 10 <= 4 && (n % 100 < 12 || n % 100 > 14)) ? 1 : ((n % 10 == 0 || n % 10 >= 5 && n % 10 <= 9 || n % 100 >= 11 && n % 100 <= 14) ? 2 : 3))':
                        $removeCategoriesWithoutExamples = true;
                        break;
                    case '(n == 1) ? 0 : ((n % 10 >= 2 && n % 10 <= 4 && (n % 100 < 12 || n % 100 > 14)) ? 1 : ((n != 1 && (n % 10 == 0 || n % 10 == 1) || n % 10 >= 5 && n % 10 <= 9 || n % 100 >= 12 && n % 100 <= 14) ? 2 : 3))':
                        $removeCategoriesWithoutExamples = true;
                        break;
                }
        }
        if (!$removeCategoriesWithoutExamples) {
            throw new Exception("Unhandled case of plural categories without examples '" . implode(', ', $badCategoriesIds) . "' out of '" . implode(', ', $allCategoriesIds) . "'");
        }
        if ($badCategories[count($badCategories) - 1]->id === CldrData::OTHER_CATEGORY) {
            $lastGood = $goodCategories[count($goodCategories) - 1];
            $lastGood->id = CldrData::OTHER_CATEGORY;
            $lastGood->formula = null;
        }
        $this->categories = $goodCategories;
    }

    private static function reverseFormula($formula)
    {
        if (preg_match('/^n( % \d+)? == \d+(\.\.\d+|,\d+)*?$/', $formula)) {
            return str_replace(' == ', ' != ', $formula);
        }
        if (preg_match('/^n( % \d+)? != \d+(\.\.\d+|,\d+)*?$/', $formula)) {
            return str_replace(' != ', ' == ', $formula);
        }
        if (preg_match('/^\(?n == \d+ \|\| n == \d+\)?$/', $formula)) {
            return trim(str_replace(array(' == ', ' || '), array(' != ', ' && '), $formula), '()');
        }
        $m = null;
        if (preg_match('/^(n(?: % \d+)?) == (\d+) && (n(?: % \d+)?) != (\d+)$/', $formula, $m)) {
            return "{$m[1]} != {$m[2]} || {$m[3]} == {$m[4]}";
        }
        switch ($formula) {
            case '(n == 1 || n == 2 || n == 3) || n % 10 != 4 && n % 10 != 6 && n % 10 != 9':
                return 'n != 1 && n != 2 && n != 3 && (n % 10 == 4 || n % 10 == 6 || n % 10 == 9)';
            case '(n == 0 || n == 1) || n >= 11 && n <= 99':
                return 'n >= 2 && (n < 11 || n > 99)';
        }
        throw new Exception("Unable to reverse the formula '{$formula}'");
    }

    private static function reduceFormula($formula)
    {
        $map = array(
            'n != 0 && n != 1' => 'n > 1',
            '(n == 0 || n == 1) && n != 0' => 'n == 1',
        );

        return isset($map[$formula]) ? $map[$formula] : $formula;
    }

    private static function asciifier(&$value)
    {
        if (is_string($value) && $value !== '') {
            $value = strtr($value, array(
                'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A',
                'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E',
                'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I',
                'Ñ' => 'N',
                'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O',
                'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U',
                'Ÿ' => 'Y', 'Ý' => 'Y',
                'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a',
                'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
                'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
                'ñ' => 'n', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
                'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
                'ý' => 'y', 'ÿ' => 'y',
                '…' => '...',
                'ʼ' => "'", '’' => "'",
            ));
        }
    }
}
