<?php

namespace Tests\Unit;

use App\Libraries\Currency\Conversion\CurrencyApi;
use Tests\TestCase;

/**
 * @test
 * @covers  App\Libraries\Currency\Conversion\CurrencyApi
 */
class CurrencyApiTest extends TestCase
{
    public function setUp() :void
    {
        parent::setUp();
    }

    public function testCurrencyConversionWorking()
    {
    	$converter = new CurrencyApi();

    	$converted_amount = $converter->convert(100,1,2);

info($converted_amount);

    	$this->assertIsFloat($converted_amount);
    }

}