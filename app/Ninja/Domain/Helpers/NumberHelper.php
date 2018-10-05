<?php

namespace App\Ninja\Domain\Helpers;

/**
 * Class NumberHelper
 * @package App\Ninja
 */
class NumberHelper
{
    /**
     * @param float $value
     * @param int $precision
     * @return float
     */
    public static function roundValue(float $value, int $precision = 2) : float
    {
        return round($value, $precision, PHP_ROUND_HALF_UP);
    }

}