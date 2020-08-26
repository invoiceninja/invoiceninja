<?php

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

    public function testParsingFloats()
    {

        Currency::all()->each(function ($currency){

            $amount = 123456789.12;

            $formatted_amount = Number::formatValue($amount, $currency);

            $float_amount = Number::parseFloat($formatted_amount);

            if($currency->precision == 0){
                $this->assertEquals(123456789, $float_amount);
            }
            else
                $this->assertEquals($amount, $float_amount);

        });

    }
}
