<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
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

    // public function testParsingFloats()
    // {
    //     Currency::all()->each(function ($currency) {
    //         $amount = 123456789.12;

    //         $formatted_amount = Number::formatValue($amount, $currency);

    //         $float_amount = Number::parseFloat($formatted_amount);

    //         if ($currency->precision == 0) {
    //             $this->assertEquals(123456789, $float_amount);
    //         } else {
    //             $this->assertEquals($amount, $float_amount);
    //         }
    //     });
    // }

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
}
