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

use App\Utils\Number;
use Tests\TestCase;

/**
 * @test
 * @covers  App\Utils\Number
 */
class NumberTest extends TestCase
{
    public function testNegativeFloatParse()
    {

        $value = '-22,00';

        $res = Number::parseFloat($value);

        $this->assertEquals(-22.0, $res);

        $value = '-22.00';

        $res = Number::parseFloat($value);

        $this->assertEquals(-22.0, $res);

        $value = '-2200,00';

        $res = Number::parseFloat($value);

        $this->assertEquals(-2200.0, $res);

        $value = '-2.200,00';

        $res = Number::parseFloat($value);

        $this->assertEquals(-2200.0, $res);
        
        $this->assertEquals(-2200, $res);
        
    }

    public function testConvertDecimalCommaFloats()
    {
        $value = '22,00';

        $res = Number::parseFloat($value);

        $this->assertEquals(22.0, $res);
        
        $value = '22.00';

        $res = Number::parseFloat($value);

        $this->assertEquals(22.0, $res);

        $value = '1,000.00';

        $res = Number::parseFloat($value);

        $this->assertEquals(1000.0, $res);

        $value = '1.000,00';

        $res = Number::parseFloat($value);

        $this->assertEquals(1000.0, $res);

    }
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

    public function testParsingStringCurrency()
    {
        $amount = '€7,99';

        $converted_amount = Number::parseFloat($amount);

        $this->assertEquals(7.99, $converted_amount);
    }

    public function testMultiCommaNumber()
    {
        $amount = '100,100.00';

        $converted_amount = Number::parseFloat($amount);

        $this->assertEquals(100100, $converted_amount);
    }

    public function testMultiDecimalNumber()
    {
        $amount = '100.1000.000,00';

        $converted_amount = Number::parseFloat($amount);

        $this->assertEquals(1001000000, $converted_amount);
    }
}
