<?php

namespace Omnipay\Rotessa\Object;

use Omnipay\Rotessa\IsValidTypeTrait;

final class BankAccountType {

    use IsValidTypeTrait;
    
    const SAVINGS = "Savings";
    const CHECKING = "Checking";

    public static function isSavings($value) {
        return $value === self::SAVINGS;
    }

    public static function isChecking($value) {
        return $value === self::Checking;
    }

    public static function getTypes() : array {
        return [
            self::SAVINGS,
            self::CHECKING
        ];
    }
}
