<?php

namespace Omnipay\Rotessa;

trait IsValidTypeTrait {
    
    public static function isValid(string $value)  {
        return in_array($value, self::getTypes());
    }

    abstract public static function getTypes() : array;
}
