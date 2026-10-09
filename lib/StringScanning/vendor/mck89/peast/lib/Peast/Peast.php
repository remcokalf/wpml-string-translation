<?php
/**
 * This file is part of the Peast package
 *
 * (c) Marco Marchiò <marco.mm89@gmail.com>
 *
 * For the full copyright and license information refer to the LICENSE file
 * distributed with this source code
 */
namespace Peast;

class Peast
{
    const SOURCE_TYPE_SCRIPT = "script";
    
    const SOURCE_TYPE_MODULE = "module";
    
    static protected $versions = array(
        "ES6" => "ES2015",
        "ES7" => "ES2016",
        "ES8" => "ES2017",
        "ES9" => "ES2018",
        "ES10" => "ES2019",
        "ES11" => "ES2020",
        "ES12" => "ES2021",
        "ES13" => "ES2022",
        "ES14" => "ES2023",
        "ES15" => "ES2024",
        "ES16" => "ES2025"
    );
    
    public static function __callStatic($version, $args)
    {
        $source = $args[0];
        $options = isset($args[1]) ? $args[1] : array();
        
        if (!in_array($version, self::$versions)) {
            if ($version === "latest") {
                $version = end(self::$versions);
            } elseif (isset(self::$versions[$version])) {
                $version = self::$versions[$version];
            } else {
                throw new \Exception("Invalid version $version");
            }
        }
        
        $featuresClass = "\\Peast\\Syntax\\$version\\Features";
        return new Syntax\Parser(
            $source, new $featuresClass, $options
        );
    }
}