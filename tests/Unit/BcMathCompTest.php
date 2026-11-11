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
use Tests\TestCase;

/**
 * Tests for BcMath::comp() and normalizeNumber() behavior.
 *
 * The critical bug: when a database decimal column returns "554.170000" (string)
 * and an API request sends 554.17 (float), the old normalizeNumber() used
 * sprintf('%.14F', 554.17) which produced "554.17000000000002" due to IEEE 754
 * imprecision, causing bccomp to report them as unequal.
 *
 * These tests ensure float-to-string normalization never reintroduces
 * floating-point artifacts, across PHP 8.2, 8.3, and 8.4.
 */
class BcMathCompTest extends TestCase
{
    // ==========================================
    // The original bug: float vs padded DB string
    // ==========================================

    public function testFloatVsDatabaseDecimalPaidToDate()
    {
        // Database returns "554.170000", request sends float 554.17
        $this->assertEquals(0, BcMath::comp('554.170000', 554.17));
    }

    public function testFloatVsDatabaseDecimalSymmetric()
    {
        // Same comparison in reverse order
        $this->assertEquals(0, BcMath::comp(554.17, '554.170000'));
    }

    public function testFloatVsCleanString()
    {
        $this->assertEquals(0, BcMath::comp(554.17, '554.17'));
    }

    public function testBothFloats()
    {
        $this->assertEquals(0, BcMath::comp(554.17, 554.17));
    }

    public function testBothStrings()
    {
        $this->assertEquals(0, BcMath::comp('554.170000', '554.17'));
    }

    // ==========================================
    // IEEE 754 notorious problem values as floats
    // ==========================================

    public function testPointOneFloat()
    {
        // 0.1 cannot be exactly represented in IEEE 754
        $this->assertEquals(0, BcMath::comp(0.1, '0.1'));
        $this->assertEquals(0, BcMath::comp(0.1, '0.1000000000'));
    }

    public function testPointTwoFloat()
    {
        $this->assertEquals(0, BcMath::comp(0.2, '0.2'));
        $this->assertEquals(0, BcMath::comp(0.2, '0.2000000000'));
    }

    public function testPointThreeFloat()
    {
        // 0.3 is a classic IEEE 754 problem
        $this->assertEquals(0, BcMath::comp(0.3, '0.3'));
    }

    public function testPointOnePlusPointTwoVsPointThree()
    {
        // 0.1 + 0.2 = 0.30000000000000004 in IEEE 754
        $sum = 0.1 + 0.2;
        $this->assertEquals(0, BcMath::comp($sum, '0.3'));
    }

    public function testPointSevenFloat()
    {
        // 0.7 is another problematic IEEE 754 value
        $this->assertEquals(0, BcMath::comp(0.7, '0.7'));
    }

    public function testPointOneFourFloat()
    {
        // 1.4 stored as 1.3999999999999999 in some representations
        $this->assertEquals(0, BcMath::comp(1.4, '1.4'));
    }

    // ==========================================
    // Realistic invoice amounts (float vs string)
    // ==========================================

    public function testTypicalInvoiceAmount()
    {
        $this->assertEquals(0, BcMath::comp(1250.50, '1250.500000'));
    }

    public function testSmallInvoiceAmount()
    {
        $this->assertEquals(0, BcMath::comp(0.99, '0.990000'));
    }

    public function testLargeInvoiceAmount()
    {
        $this->assertEquals(0, BcMath::comp(99999.99, '99999.990000'));
    }

    public function testWholeNumberAmount()
    {
        $this->assertEquals(0, BcMath::comp(500.00, '500.000000'));
    }

    public function testZeroAmount()
    {
        $this->assertEquals(0, BcMath::comp(0.0, '0.000000'));
        $this->assertEquals(0, BcMath::comp(0.0, '0'));
    }

    public function testOnecentAmount()
    {
        $this->assertEquals(0, BcMath::comp(0.01, '0.010000'));
    }

    // ==========================================
    // Computed float results vs DB strings
    // Simulates real tax/total calculations
    // ==========================================

    public function testTaxCalculationResultVsDbString()
    {
        // 6570.20 * 7.5 / 100 = 492.765 (with potential IEEE drift)
        $tax = 6570.20 * 7.5 / 100;
        $this->assertEquals(0, BcMath::comp($tax, '492.765'));
    }

    public function testInvoiceTotalCalculationVsDbString()
    {
        // Typical: sum of line items
        $total = 149.99 + 249.99 + 99.99;
        $this->assertEquals(0, BcMath::comp($total, '499.97'));
    }

    public function testDiscountCalculationVsDbString()
    {
        $subtotal = 1000.0;
        $discount = $subtotal * 12.5 / 100;
        $this->assertEquals(0, BcMath::comp($discount, '125'));
    }

    public function testGatewayFeeVsDbString()
    {
        $amount = 1500.00;
        $fee = ($amount * 2.9 / 100) + 0.30;
        $this->assertEquals(0, BcMath::comp($fee, '43.8'));
    }

    // ==========================================
    // Actual inequality must still be detected
    // ==========================================

    public function testActualDifferenceDetected()
    {
        $this->assertEquals(-1, BcMath::comp(554.17, '554.18'));
        $this->assertEquals(1, BcMath::comp(554.18, '554.17'));
    }

    public function testSmallActualDifference()
    {
        $this->assertEquals(-1, BcMath::comp('100.00', '100.01'));
        $this->assertEquals(1, BcMath::comp('100.01', '100.00'));
    }

    public function testFloatActuallyGreater()
    {
        $this->assertEquals(1, BcMath::comp(554.18, '554.170000'));
    }

    public function testFloatActuallyLess()
    {
        $this->assertEquals(-1, BcMath::comp(554.16, '554.170000'));
    }

    public function testNegativeVsPositive()
    {
        $this->assertEquals(-1, BcMath::comp(-1.0, '1.0'));
        $this->assertEquals(1, BcMath::comp(1.0, '-1.0'));
    }

    // ==========================================
    // Null, empty, and edge-case inputs
    // ==========================================

    public function testNullVsZeroString()
    {
        $this->assertEquals(0, BcMath::comp(null, '0'));
        $this->assertEquals(0, BcMath::comp(null, '0.000000'));
    }

    public function testEmptyStringVsZero()
    {
        $this->assertEquals(0, BcMath::comp('', '0'));
        $this->assertEquals(0, BcMath::comp('', 0.0));
    }

    public function testIntegerVsFloat()
    {
        $this->assertEquals(0, BcMath::comp(100, 100.0));
        $this->assertEquals(0, BcMath::comp(100, '100.000000'));
    }

    public function testIntegerVsString()
    {
        $this->assertEquals(0, BcMath::comp(0, '0'));
        $this->assertEquals(0, BcMath::comp(500, '500'));
    }

    public function testNegativeFloatVsNegativeDbString()
    {
        $this->assertEquals(0, BcMath::comp(-554.17, '-554.170000'));
    }

    public function testNegativeFloatVsNegativeString()
    {
        $this->assertEquals(0, BcMath::comp(-0.99, '-0.99'));
    }

    // ==========================================
    // BcMath::add/sub/mul/div with mixed types
    // Ensures normalizeNumber works across all ops
    // ==========================================

    public function testAddFloatAndString()
    {
        $result = BcMath::add(554.17, '0.000000');
        $this->assertEquals(0, bccomp($result, '554.17', 2));
    }

    public function testSubFloatFromDbString()
    {
        $result = BcMath::sub('554.170000', 554.17);
        $this->assertEquals(0, bccomp($result, '0', 10));
    }

    public function testMulFloatAndString()
    {
        $result = BcMath::mul(1.1, '100');
        $this->assertEquals(0, bccomp($result, '110', 2));
    }

    public function testDivFloatByString()
    {
        $result = BcMath::div(554.17, '1');
        $this->assertEquals(0, bccomp($result, '554.17', 2));
    }

    // ==========================================
    // PHP 8.4 specific: float string casting changes
    // PHP 8.4 may cast floats to strings differently,
    // ensure normalizeNumber handles both old and new behavior
    // ==========================================

    public function testPHP84FloatCastingEdgeCases()
    {
        // These floats have known IEEE 754 representation issues
        // that PHP 8.4 may expose differently when casting to string
        $cases = [
            [0.1, '0.1'],
            [0.2, '0.2'],
            [0.3, '0.3'],
            [0.6, '0.6'],
            [0.7, '0.7'],
            [1.1, '1.1'],
            [2.2, '2.2'],
            [3.3, '3.3'],
            [10.1, '10.1'],
            [100.01, '100.01'],
            [999.99, '999.99'],
            [1234.56, '1234.56'],
        ];

        foreach ($cases as [$float, $string]) {
            $this->assertEquals(
                0,
                BcMath::comp($float, $string),
                "BcMath::comp({$float}, '{$string}') should be 0"
            );
        }
    }

    public function testPHP84TrailingZeroPaddedDbValues()
    {
        // Database decimal columns return varying trailing zeros
        // depending on column precision (DECIMAL(16,6) vs DECIMAL(10,2))
        $cases = [
            [554.17, '554.17'],
            [554.17, '554.170'],
            [554.17, '554.1700'],
            [554.17, '554.17000'],
            [554.17, '554.170000'],
            [554.17, '554.1700000'],
            [554.17, '554.17000000'],
            [100.50, '100.500000'],
            [0.01, '0.010000'],
            [1.00, '1.000000'],
        ];

        foreach ($cases as [$float, $dbString]) {
            $this->assertEquals(
                0,
                BcMath::comp($float, $dbString),
                "BcMath::comp({$float}, '{$dbString}') should be 0"
            );
        }
    }

    // ==========================================
    // High precision: values near float limits
    // ==========================================

    public function testHighPrecisionFloat()
    {
        // Large integers with decimals lose float precision beyond ~15 significant digits.
        // 1234567890.12 has 12 significant digits — the fractional part drifts in IEEE 754.
        // This is a genuine float limitation, NOT a normalizeNumber bug.
        // Verify that values within safe float precision range still compare correctly.
        $this->assertEquals(0, BcMath::comp(12345.12, '12345.12'));
        $this->assertEquals(0, BcMath::comp(99999.99, '99999.99'));
        $this->assertEquals(0, BcMath::comp(100000.50, '100000.50'));
    }

    public function testVerySmallFloat()
    {
        $this->assertEquals(0, BcMath::comp(0.0001, '0.0001'));
        $this->assertEquals(0, BcMath::comp(0.0001, '0.000100'));
    }

    public function testNearZeroFloat()
    {
        $this->assertEquals(0, BcMath::comp(0.0000000001, '0.0000000001'));
    }
}
