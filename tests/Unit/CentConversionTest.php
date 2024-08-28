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

use Tests\TestCase;

/**
 * @test
 */
class CentConversionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testConversionOfDecimalValues()
    {
        $precision = 2;

        $amount = 10.20;
        $amount = round(($amount * pow(10, $precision)), 0);

        $this->assertEquals(1020, $amount);

        $amount = 2;
        $amount = round(($amount * pow(10, $precision)), 0);

        $this->assertEquals(200, $amount);

        $amount = 2.12;
        $amount = round(($amount * pow(10, $precision)), 0);

        $this->assertEquals(212, $amount);
    }

    public function testBcMathWay()
    {
        $amount = 64.99;
        $amount = bcmul($amount, 100);

        $this->assertEquals(6499, $amount);

        $amount = 2;
        $amount = bcmul($amount, 100);

        $this->assertEquals(200, $amount);

        $amount = 2.12;
        $amount = bcmul($amount, 100);

        $this->assertEquals(212, $amount);
    }
}
