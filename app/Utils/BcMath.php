<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Utils;

/**
 * BcMath utility class for precise financial calculations
 * 
 * This class provides static methods to replace float arithmetic and comparisons
 * with bcmath equivalents for consistent and accurate monetary calculations.
 * 
 * All methods use a default scale of 2 decimal places for currency calculations.
 * You can override the scale for specific calculations if needed.
 */
class BcMath
{
    /**
     * Default scale for currency calculations (2 decimal places)
     */
    private const DEFAULT_SCALE = 2;

    /**
     * Add two numbers using bcmath
     * 
     * @param string|float|int $left
     * @param string|float|int $right
     * @param int|null $scale
     * @return string
     */
    public static function add($left, $right, ?int $scale = null): string
    {
        $scale = $scale ?? self::DEFAULT_SCALE;
        return bcadd((string)$left, (string)$right, $scale);
    }

    /**
     * Subtract two numbers using bcmath
     * 
     * @param string|float|int $left
     * @param string|float|int $right
     * @param int|null $scale
     * @return string
     */
    public static function sub($left, $right, ?int $scale = null): string
    {
        $scale = $scale ?? self::DEFAULT_SCALE;
        return bcsub((string)$left, (string)$right, $scale);
    }

    /**
     * Multiply two numbers using bcmath
     * 
     * @param string|float|int $left
     * @param string|float|int $right
     * @param int|null $scale
     * @return string
     */
    public static function mul($left, $right, ?int $scale = null): string
    {
        $scale = $scale ?? self::DEFAULT_SCALE;
        return bcmul((string)$left, (string)$right, $scale);
    }

    /**
     * Divide two numbers using bcmath
     * 
     * @param string|float|int $left
     * @param string|float|int $right
     * @param int|null $scale
     * @return string
     */
    public static function div($left, $right, ?int $scale = null): string
    {
        $scale = $scale ?? self::DEFAULT_SCALE;
        return bcdiv((string)$left, (string)$right, $scale);
    }

    /**
     * Calculate modulo using bcmath
     * 
     * @param string|float|int $left
     * @param string|float|int $right
     * @param int|null $scale
     * @return string
     */
    public static function mod($left, $right, ?int $scale = null): string
    {
        $scale = $scale ?? self::DEFAULT_SCALE;
        return bcmod((string)$left, (string)$right, $scale);
    }

    /**
     * Calculate power using bcmath
     * 
     * @param string|float|int $base
     * @param string|float|int $exponent
     * @param int|null $scale
     * @return string
     */
    public static function pow($base, $exponent, ?int $scale = null): string
    {
        $scale = $scale ?? self::DEFAULT_SCALE;
        return bcpow((string)$base, (string)$exponent, $scale);
    }

    /**
     * Calculate square root using bcmath
     * 
     * @param string|float|int $number
     * @param int|null $scale
     * @return string
     */
    public static function sqrt($number, ?int $scale = null): string
    {
        $scale = $scale ?? self::DEFAULT_SCALE;
        return bcsqrt((string)$number, $scale);
    }

    /**
     * Round a number to specified decimal places using bcmath
     * 
     * @param string|float|int $number
     * @param int $precision
     * @return string
     */
    public static function round($number, int $precision = self::DEFAULT_SCALE): string
    {
        $number = (string)$number;
        $scale = $precision + 1; // Add one extra decimal for rounding
        
        // Multiply by 10^scale, add 0.5, floor, then divide by 10^scale
        $multiplier = bcpow('10', (string)$scale, 0);
        $rounded = bcadd(bcmul($number, $multiplier, 0), '0.5', 0);
        $result = bcdiv($rounded, $multiplier, $precision);
        
        return $result;
    }

    /**
     * Compare two numbers using bcmath
     * 
     * @param string|float|int $left
     * @param string|float|int $right
     * @param int|null $scale
     * @return int Returns -1, 0, or 1 if left is less than, equal to, or greater than right
     */
    public static function comp($left, $right, ?int $scale = null): int
    {
        $scale = $scale ?? self::DEFAULT_SCALE;
        return bccomp((string)$left, (string)$right, $scale);
    }

    /**
     * Check if two numbers are equal using bcmath
     * 
     * @param string|float|int $left
     * @param string|float|int $right
     * @param int|null $scale
     * @return bool
     */
    public static function equal($left, $right, ?int $scale = null): bool
    {
        return self::comp($left, $right, $scale) === 0;
    }

    /**
     * Check if left number is greater than right number using bcmath
     * 
     * @param string|float|int $left
     * @param string|float|int $right
     * @param int|null $scale
     * @return bool
     */
    public static function greaterThan($left, $right, ?int $scale = null): bool
    {
        return self::comp($left, $right, $scale) === 1;
    }

    /**
     * Check if left number is greater than or equal to right number using bcmath
     * 
     * @param string|float|int $left
     * @param string|float|int $right
     * @param int|null $scale
     * @return bool
     */
    public static function greaterThanOrEqual($left, $right, ?int $scale = null): bool
    {
        return self::comp($left, $right, $scale) >= 0;
    }

    /**
     * Check if left number is less than right number using bcmath
     * 
     * @param string|float|int $left
     * @param string|float|int $right
     * @param int|null $scale
     * @return bool
     */
    public static function lessThan($left, $right, ?int $scale = null): bool
    {
        return self::comp($left, $right, $scale) === -1;
    }

    /**
     * Check if left number is less than or equal to right number using bcmath
     * 
     * @param string|float|int $left
     * @param string|float|int $right
     * @param int|null $scale
     * @return bool
     */
    public static function lessThanOrEqual($left, $right, ?int $scale = null): bool
    {
        return self::comp($left, $right, $scale) <= 0;
    }

    /**
     * Check if a number is zero using bcmath
     * 
     * @param string|float|int $number
     * @param int|null $scale
     * @return bool
     */
    public static function isZero($number, ?int $scale = null): bool
    {
        return self::equal($number, '0', $scale);
    }

    /**
     * Check if a number is positive using bcmath
     * 
     * @param string|float|int $number
     * @param int|null $scale
     * @return bool
     */
    public static function isPositive($number, ?int $scale = null): bool
    {
        return self::greaterThan($number, '0', $scale);
    }

    /**
     * Check if a number is negative using bcmath
     * 
     * @param string|float|int $number
     * @param int|null $scale
     * @return bool
     */
    public static function isNegative($number, ?int $scale = null): bool
    {
        return self::lessThan($number, '0', $scale);
    }

    /**
     * Get the absolute value using bcmath
     * 
     * @param string|float|int $number
     * @param int|null $scale
     * @return string
     */
    public static function abs($number, ?int $scale = null): string
    {
        $scale = $scale ?? self::DEFAULT_SCALE;
        $number = (string)$number;
        
        if (self::isNegative($number, $scale)) {
            return self::mul($number, '-1', $scale);
        }
        
        return $number;
    }

    /**
     * Calculate percentage using bcmath
     * 
     * @param string|float|int $part
     * @param string|float|int $total
     * @param int|null $scale
     * @return string
     */
    public static function percentage($part, $total, ?int $scale = null): string
    {
        $scale = $scale ?? self::DEFAULT_SCALE;
        
        if (self::isZero($total, $scale)) {
            return '0';
        }
        
        return self::mul(self::div($part, $total, $scale + 2), '100', $scale);
    }

    /**
     * Calculate sum of an array of numbers using bcmath
     * 
     * @param array $numbers
     * @param int|null $scale
     * @return string
     */
    public static function sum(array $numbers, ?int $scale = null): string
    {
        $scale = $scale ?? self::DEFAULT_SCALE;
        $result = '0';
        
        foreach ($numbers as $number) {
            $result = self::add($result, $number, $scale);
        }
        
        return $result;
    }

    /**
     * Calculate average of an array of numbers using bcmath
     * 
     * @param array $numbers
     * @param int|null $scale
     * @return string
     */
    public static function avg(array $numbers, ?int $scale = null): string
    {
        $scale = $scale ?? self::DEFAULT_SCALE;
        $count = count($numbers);
        
        if ($count === 0) {
            return '0';
        }
        
        $sum = self::sum($numbers, $scale);
        return self::div($sum, (string)$count, $scale);
    }

    /**
     * Format a number as currency string with proper precision
     * 
     * @param string|float|int $number
     * @param int $precision
     * @return string
     */
    public static function formatCurrency($number, int $precision = self::DEFAULT_SCALE): string
    {
        $rounded = self::round($number, $precision);
        return number_format((float)$rounded, $precision, '.', '');
    }

    /**
     * Convert a number to float for compatibility with existing code
     * Use this sparingly and only when you need to pass values to functions that expect floats
     * 
     * @param string|float|int $number
     * @return float
     */
    public static function toFloat($number): float
    {
        return (float)$number;
    }

    /**
     * Convert a number to string for consistent handling
     * 
     * @param string|float|int $number
     * @return string
     */
    public static function toString($number): string
    {
        return (string)$number;
    }
}
