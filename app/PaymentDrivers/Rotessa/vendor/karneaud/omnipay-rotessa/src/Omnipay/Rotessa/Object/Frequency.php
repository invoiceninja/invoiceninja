<?php

namespace Omnipay\Rotessa\Object;

use Omnipay\Rotessa\IsValidTypeTrait;

final class Frequency {
    
    use IsValidTypeTrait;

    const ONCE = "Once";
    const WEEKLY = "Weekly";
    const OTHER_WEEK = "Every Other Week";
    const MONTHLY= "Monthly"; 
    const OTHER_MONTH =	"Every Other Month";
    const QUARTERLY = "Quarterly";
    const SEMI_ANNUALLY = "Semi-Annually";
    const YEARLY = "Yearly";

    public static function isOnce($value) {
        return $value === self::ONCE;
    }

    public static function isWeekly($value) {
        return $value === self::WEEKLY;
    }

    public static function isOtherWeek($value) {
        return $value === self::OTHER_WEEK;
    }

    public static function isMonthly($value) {
        return $value === self::MONTHLY;
    }

    public static function isOtherMonth($value) {
        return $value === self::OTHER_MONTH;
    }

    public static function isQuarterly($value) {
        return $value === self::QUARTERLY;
    }

    public static function isSemiAnnually($value) {
        return $value === self::SEMI_ANNUALLY;
    }

    public static function isYearly($value) {
        return $value === self::YEARLY;
    }

    public static function getTypes() : array {
        return [
            self::ONCE,
            self::WEEKLY,
            self::OTHER_WEEK,
            self::MONTHLY,
            self::OTHER_MONTH,
            self::QUARTERLY,
            self::SEMI_ANNUALLY,
            self::YEARLY
        ];
    }
}
