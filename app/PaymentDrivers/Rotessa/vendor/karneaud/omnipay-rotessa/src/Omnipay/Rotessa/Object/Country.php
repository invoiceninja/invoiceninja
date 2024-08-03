<?php

namespace Omnipay\Rotessa\Object;

use Omnipay\Rotessa\IsValidTypeTrait;

final class Country {

    use IsValidTypeTrait;
    
    protected static $codes = ['CA','US'];
    protected static $names = ['United States', 'Canada'];

    public static function isValidCountryName(string $value) {
        return in_array($value, self::$names);
    }

    public static function isValidCountryCode(string $value) {
        return in_array($value, self::$codes);
    }

    public static function isAmerican(string $value) : bool {
        return $value == 'US' || $value == 'United States';
    }

    public static function isCanadian(string $value) : bool {
        return $value == 'CA' || $value == 'Canada';
    }

    public static function getTypes() : array {
        return $codes + $names;
    }
}
