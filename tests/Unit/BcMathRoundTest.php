<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Unit;

use App\Utils\BcMath;
use App\Utils\Number;
use Tests\TestCase;

/**
 * Tests for BcMath::round() and Number::roundValue()
 *
 * These tests ensure consistent rounding behavior across PHP 8.2, 8.3, and 8.4.
 * PHP 8.4 removed internal "pre-rounding" in the native round() function, which
 * causes computed floats with IEEE 754 drift (e.g. 492.764999... displaying as
 * 492.765) to round differently. BcMath::round() operates on string representations
 * to avoid this entirely.
 */
class BcMathRoundTest extends TestCase
{
    // ==========================================
    // BcMath::round() — string-based rounding
    // ==========================================

    public function testRoundHalfUp()
    {
        $this->assertEquals('492.77', BcMath::round('492.765', 2));
    }

    public function testRoundHalfDown()
    {
        $this->assertEquals('492.76', BcMath::round('492.764', 2));
    }

    public function testRoundExactHalf()
    {
        $this->assertEquals('492.78', BcMath::round('492.775', 2));
    }

    public function testRoundNegativeHalfUp()
    {
        $this->assertEquals('-492.77', BcMath::round('-492.765', 2));
    }

    public function testRoundNegativeHalfDown()
    {
        $this->assertEquals('-492.76', BcMath::round('-492.764', 2));
    }

    public function testRoundZero()
    {
        $this->assertEquals('0.00', BcMath::round('0', 2));
    }

    public function testRoundPrecisionZeroUp()
    {
        $this->assertEquals('493', BcMath::round('492.5', 0));
    }

    public function testRoundPrecisionZeroDown()
    {
        $this->assertEquals('492', BcMath::round('492.4', 0));
    }

    public function testRoundPrecisionFour()
    {
        $this->assertEquals('0.1235', BcMath::round('0.12345', 4));
    }

    public function testRoundSmallValueAtBoundary()
    {
        $this->assertEquals('0.01', BcMath::round('0.005', 2));
    }

    public function testRoundCarryOver()
    {
        $this->assertEquals('1000000.00', BcMath::round('999999.995', 2));
    }

    public function testRoundAlreadyRounded()
    {
        $this->assertEquals('492.77', BcMath::round('492.77', 2));
    }

    public function testRoundTrailingZeros()
    {
        $this->assertEquals('10.10', BcMath::round('10.10', 2));
    }

    public function testRoundInteger()
    {
        $this->assertEquals('500.00', BcMath::round('500', 2));
    }

    public function testRoundPrecisionThreeUp()
    {
        $this->assertEquals('1.235', BcMath::round('1.2345', 3));
    }

    public function testRoundPrecisionThreeDown()
    {
        $this->assertEquals('1.234', BcMath::round('1.2344', 3));
    }

    public function testRoundHalfAtPrecisionZero()
    {
        $this->assertEquals('3', BcMath::round('2.5', 0));
    }

    public function testRoundNegativeHalfAtPrecisionZero()
    {
        $this->assertEquals('-3', BcMath::round('-2.5', 0));
    }

    public function testRoundCascadeCarry()
    {
        $this->assertEquals('10.00', BcMath::round('9.999', 2));
    }

    public function testRoundNinesAboveBoundary()
    {
        $this->assertEquals('2.00', BcMath::round('1.9999', 2));
    }

    public function testRoundNinesBelowBoundary()
    {
        $this->assertEquals('1.99', BcMath::round('1.9949', 2));
    }

    public function testRoundExactHalfWithTrailingZeros()
    {
        $this->assertEquals('2.00', BcMath::round('1.9950', 2));
    }

    public function testRoundNegativeSmallValue()
    {
        $this->assertEquals('-1', BcMath::round('-0.5', 0));
    }

    public function testRoundPositiveSmallValue()
    {
        $this->assertEquals('1', BcMath::round('0.5', 0));
    }

    public function testRoundSubCentPositive()
    {
        $this->assertEquals('0', BcMath::round('0.05', 0));
    }

    public function testRoundLargeNumber()
    {
        $this->assertEquals('1234567890.12', BcMath::round('1234567890.123', 2));
    }

    public function testRoundLargeNumberUp()
    {
        $this->assertEquals('1234567890.13', BcMath::round('1234567890.125', 2));
    }

    public function testRoundPiToFive()
    {
        $this->assertEquals('3.14159', BcMath::round('3.141592653589793', 5));
    }

    // ==========================================
    // PHP 8.4 regression cases — computed floats
    // These are the critical tests that would FAIL
    // with native round() on PHP 8.4 but PASS with
    // BcMath::round() via Number::roundValue()
    // ==========================================

    /**
     * The original regression case.
     * A computed float that displays as 492.765 but is stored as 492.764999...
     * Native round() on PHP 8.4 returns 492.76; BcMath must return 492.77.
     */
    public function testPHP84RegressionOriginalCase()
    {
        $this->assertEquals('492.77', BcMath::round('492.765', 2));
        $this->assertEquals(492.77, Number::roundValue(492.765, 2));
    }

    /**
     * Simulates a tax calculation: amount * rate / 100
     * 6570 * 7.5 / 100 = 492.75 (exact), but multi-step float
     * arithmetic can drift.
     */
    public function testTaxCalculationPrecision()
    {
        $amount = 6570.20;
        $rate = 7.5;
        $tax = $amount * $rate / 100;

        $result = Number::roundValue($tax, 2);
        $this->assertEquals(round($amount * $rate / 100, 2, PHP_ROUND_HALF_UP), $result);
    }

    /**
     * Classic problematic case: 0.1 + 0.2 != 0.3 in IEEE 754
     */
    public function testIEEE754ClassicDrift()
    {
        $value = 0.1 + 0.2; // 0.30000000000000004
        $result = Number::roundValue($value, 2);
        $this->assertEquals(0.30, $result);
    }

    /**
     * Division that produces a value at the .5 boundary
     * 7380 / 28800 * 100 = 25.625 but float stores as 25.624999...
     */
    public function testDivisionBoundaryDrift()
    {
        $value = (7380 / 28800) * 100;
        $result = Number::roundValue($value, 2);
        $this->assertEquals(25.63, $result);
    }

    /**
     * Multiplication that lands on .5 boundary
     */
    public function testMultiplicationBoundaryDrift()
    {
        $value = 1.15 * 100; // Should be 115.0 but can drift
        $result = Number::roundValue($value, 2);
        $this->assertEquals(115.0, $result);
    }

    /**
     * Inclusive tax reverse calculation
     * amount - (amount / (1 + rate/100))
     */
    public function testInclusiveTaxReverseCalculation()
    {
        $amount = 107.50;
        $rate = 7.5;
        $tax = $amount - ($amount / (1 + ($rate / 100)));
        $result = Number::roundValue($tax, 2);
        $this->assertEquals(7.50, $result);
    }

    // ==========================================
    // Number::roundValue() — float wrapper
    // ==========================================

    public function testNumberRoundValueBasic()
    {
        $this->assertEquals(492.77, Number::roundValue(492.765, 2));
    }

    public function testNumberRoundValueDown()
    {
        $this->assertEquals(492.76, Number::roundValue(492.764, 2));
    }

    public function testNumberRoundValueNegative()
    {
        $this->assertEquals(-492.77, Number::roundValue(-492.765, 2));
    }

    public function testNumberRoundValueDefaultPrecision()
    {
        $this->assertEquals(492.77, Number::roundValue(492.765));
    }

    public function testNumberRoundValuePrecisionThree()
    {
        $this->assertEquals(3.145, Number::roundValue(3.144944444444, 3));
    }

    public function testNumberRoundValuePrecisionThreeLow()
    {
        $this->assertEquals(3.144, Number::roundValue(3.144444444444, 3));
    }

    public function testNumberRoundValueZero()
    {
        $this->assertEquals(0.00, Number::roundValue(0.0, 2));
    }

    public function testNumberRoundValueSmallAmount()
    {
        $this->assertEquals(0.01, Number::roundValue(0.005, 2));
    }

    /**
     * Ensures Number::roundValue matches PHP_ROUND_HALF_UP behavior
     * for values where native round() is unambiguous (no drift)
     */
    public function testNumberRoundValueMatchesNativeForCleanValues()
    {
        $cases = [
            [1.5, 0, 2.0],
            [2.5, 0, 3.0],
            [-1.5, 0, -2.0],
            [-2.5, 0, -3.0],
            [1.25, 1, 1.3],
            [1.35, 1, 1.4],
            [1.45, 1, 1.5],
            [10.0, 2, 10.0],
            [99.99, 2, 99.99],
            [100.005, 2, 100.01],
        ];

        foreach ($cases as [$value, $precision, $expected]) {
            $this->assertEquals(
                $expected,
                Number::roundValue($value, $precision),
                "Failed for roundValue({$value}, {$precision})"
            );
        }
    }

    // ==========================================
    // Cross-version consistency
    // BcMath::round() on strings must always match
    // round($literal, $p, PHP_ROUND_HALF_UP) where
    // the literal has no IEEE 754 drift.
    // ==========================================

    /**
     * Comprehensive data set ensuring BcMath::round() matches the
     * mathematically correct result for exact decimal strings.
     */
    public function testBcMathRoundComprehensiveDataSet()
    {
        $cases = [
            // [input_string, precision, expected]
            ['0.5', 0, '1'],
            ['1.5', 0, '2'],
            ['2.5', 0, '3'],
            ['-0.5', 0, '-1'],
            ['-1.5', 0, '-2'],
            ['-2.5', 0, '-3'],
            ['0.05', 1, '0.1'],
            ['0.15', 1, '0.2'],
            ['0.25', 1, '0.3'],
            ['0.35', 1, '0.4'],
            ['0.45', 1, '0.5'],
            ['0.55', 1, '0.6'],
            ['0.65', 1, '0.7'],
            ['0.75', 1, '0.8'],
            ['0.85', 1, '0.9'],
            ['0.95', 1, '1.0'],
            ['0.005', 2, '0.01'],
            ['0.015', 2, '0.02'],
            ['0.025', 2, '0.03'],
            ['0.035', 2, '0.04'],
            ['0.045', 2, '0.05'],
            ['0.055', 2, '0.06'],
            ['0.065', 2, '0.07'],
            ['0.075', 2, '0.08'],
            ['0.085', 2, '0.09'],
            ['0.095', 2, '0.10'],
            ['100.125', 2, '100.13'],
            ['100.135', 2, '100.14'],
            ['100.145', 2, '100.15'],
            ['100.155', 2, '100.16'],
            ['100.165', 2, '100.17'],
            ['100.175', 2, '100.18'],
            ['100.185', 2, '100.19'],
            ['100.195', 2, '100.20'],
            ['999.995', 2, '1000.00'],
            ['9999.995', 2, '10000.00'],
            ['0.0005', 3, '0.001'],
            ['1.2345', 3, '1.235'],
            ['1.2344', 3, '1.234'],
            ['99.9995', 3, '100.000'],
        ];

        foreach ($cases as [$input, $precision, $expected]) {
            $this->assertEquals(
                $expected,
                BcMath::round($input, $precision),
                "BcMath::round('{$input}', {$precision}) should be '{$expected}'"
            );
        }
    }

    // ==========================================
    // Invoice-specific rounding scenarios
    // ==========================================

    /**
     * Typical invoice: cost * qty with tax
     */
    public function testInvoiceLineItemWithTax()
    {
        $cost = 49.50;
        $qty = 3;
        $tax_rate = 19.0;

        $line_total = $cost * $qty;
        $tax = $line_total * $tax_rate / 100;

        $rounded_total = Number::roundValue($line_total, 2);
        $rounded_tax = Number::roundValue($tax, 2);

        $this->assertEquals(148.50, $rounded_total);
        $this->assertEquals(28.22, $rounded_tax);
    }

    /**
     * Discount calculation: percentage discount on line total
     */
    public function testDiscountCalculation()
    {
        $line_total = 250.00;
        $discount = 12.5; // percent

        $discount_amount = $line_total * $discount / 100;
        $result = Number::roundValue($discount_amount, 2);

        $this->assertEquals(31.25, $result);
    }

    /**
     * Gateway fee: typical credit card fee calculation
     */
    public function testGatewayFeeCalculation()
    {
        $amount = 1500.00;
        $fee_percent = 2.9;
        $fee_fixed = 0.30;

        $fee = ($amount * $fee_percent / 100) + $fee_fixed;
        $result = Number::roundValue($fee, 2);

        $this->assertEquals(43.80, $result);
    }

    /**
     * Pro-rata calculation: partial period billing
     */
    public function testProRataCalculation()
    {
        $annual = 1200.00;
        $days_used = 45;
        $days_in_year = 365;

        $prorata = $annual * $days_used / $days_in_year;
        $result = Number::roundValue($prorata, 2);

        $this->assertEquals(147.95, $result);
    }

    /**
     * Currency with 0 precision (JPY)
     */
    public function testZeroPrecisionCurrency()
    {
        $this->assertEquals(493.0, Number::roundValue(492.5, 0));
        $this->assertEquals(492.0, Number::roundValue(492.4, 0));
        $this->assertEquals('1000', BcMath::round('999.5', 0));
    }
}
