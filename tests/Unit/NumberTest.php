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
}
