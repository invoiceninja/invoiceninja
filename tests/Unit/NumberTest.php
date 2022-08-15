<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Unit;

use App\Models\Currency;
use App\Utils\Number;
use Tests\TestCase;

/**
 * @test
 * @covers  App\Utils\Number
 */
class NumberTest extends TestCase
{

    public function testFloatPrecision()
    {
        $value = 1.1;

        $precision = (int) strpos(strrev($value), ".");

        $result =  round($value, $precision);

        $this->assertEquals(1.1, $result);
    }


    public function testFloatPrecision1()
    {
        $value = "1.1";

        $precision = (int) strpos(strrev($value), ".");

        $result =  round($value, $precision);

        $this->assertEquals(1.1, $result);
    }


    public function testFloatPrecision2()
    {
        $value = 9.975;

        $precision = (int) strpos(strrev($value), ".");

        $result =  round($value, $precision);

        $this->assertEquals(9.975, $result);
    }

    public function testFloatPrecision3()
    {
        $value = "9.975";

        $precision = (int) strpos(strrev($value), ".");

        $result =  round($value, $precision);

        $this->assertEquals(9.975, $result);
    }

    public function testRoundingThreeLow()
    {
        $rounded = Number::roundValue(3.144444444444, 3);

        $this->assertEquals(3.144, $rounded);
    }

    public function testRoundingThreeHigh()
    {
        $rounded = Number::roundValue(3.144944444444, 3);

        $this->assertEquals(3.145, $rounded);
    }

    public function testRoundingTwoLow()
    {
        $rounded = Number::roundValue(2.145);

        $this->assertEquals(2.15, $rounded);
    }

    //this method proved an error! removing this method from production
    // public function testImportFloatConversion()
    // {

    //     $amount = '€7,99';

    //     $converted_amount = Number::parseStringFloat($amount);

    //     $this->assertEquals(799, $converted_amount);

    // }

    public function testParsingStringCurrency()
    {
        $amount = '€7,99';

        $converted_amount = Number::parseFloat($amount);

        $this->assertEquals(7.99, $converted_amount);
    }

    public function testRoundingDecimalsTwo()
    {
        $currency = Currency::find(1);

        $x = Number::formatValueNoTrailingZeroes(0.05, $currency);

        $this->assertEquals(0.05, $x);
    }

    public function testRoundingDecimalsThree()
    {
        $currency = Currency::find(1);

        $x = Number::formatValueNoTrailingZeroes(0.005, $currency);

        $this->assertEquals(0.005, $x);
    }

    public function testRoundingDecimalsFour()
    {
        $currency = Currency::find(1);

        $x = Number::formatValueNoTrailingZeroes(0.0005, $currency);

        $this->assertEquals(0.0005, $x);
    }

    public function testRoundingDecimalsFive()
    {
        $currency = Currency::find(1);

        $x = Number::formatValueNoTrailingZeroes(0.00005, $currency);

        $this->assertEquals(0.00005, $x);
    }

    public function testRoundingDecimalsSix()
    {
        $currency = Currency::find(1);

        $x = Number::formatValueNoTrailingZeroes(0.000005, $currency);

        $this->assertEquals(0.000005, $x);
    }

    public function testRoundingDecimalsSeven()
    {
        $currency = Currency::find(1);

        $x = Number::formatValueNoTrailingZeroes(0.0000005, $currency);

        $this->assertEquals(0.0000005, $x);
    }

    public function testRoundingDecimalsEight()
    {
        $currency = Currency::find(1);

        $x = Number::formatValueNoTrailingZeroes(0.00000005, $currency);

        $this->assertEquals(0.00000005, $x);
    }

    public function testRoundingPositive()
    {
        $currency = Currency::find(1);

        $x = Number::formatValueNoTrailingZeroes(1.5, $currency);
        $this->assertEquals(1.5, $x);

        $x = Number::formatValueNoTrailingZeroes(1.50, $currency);
        $this->assertEquals(1.5, $x);

        $x = Number::formatValueNoTrailingZeroes(1.500, $currency);
        $this->assertEquals(1.5, $x);

        $x = Number::formatValueNoTrailingZeroes(1.50005, $currency);
        $this->assertEquals(1.50005, $x);

        $x = Number::formatValueNoTrailingZeroes(1.50000005, $currency);
        $this->assertEquals(1.50000005, $x);
    }
}
