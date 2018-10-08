<?php

namespace Tests\Unit;

use App\Utils\NumberHelper;
use Tests\TestCase;

/**
 * @test
 * @covers  App\Utils\NumberHelper
 */
class NumberTest extends TestCase
{
    public function testRoundingThreeLow()
    {
        $rounded = NumberHelper::roundValue(3.144444444444, 3);

        $this->assertEquals(3.144, $rounded);
    }

    public function testRoundingThreeHigh()
    {
        $rounded = NumberHelper::roundValue(3.144944444444, 3);

        $this->assertEquals(3.145, $rounded);
    }

    public function testRoundingTwoLow()
    {
        $rounded = NumberHelper::roundValue(2.145);

        $this->assertEquals(2.15, $rounded);
    }
}
