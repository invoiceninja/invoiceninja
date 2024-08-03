<?php

namespace Omnipay\Rotessa\Object;

use Omnipay\Rotessa\IsValidTypeTrait;

final class CustomerType {

    use IsValidTypeTrait;
    
    const PERSONAL = "Personal";
    const BUSINESS = "Business";

    public static function isPersonal($value) {
        return $value === self::PERSONAL;
    }

    public static function isBusiness($value) {
        return $value === self::BUSINESS;
    }

    public static function getTypes() : array {
        return [
            self::PERSONAL,
            self::BUSINESS
        ];
    }
}
