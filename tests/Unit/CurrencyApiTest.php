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

use App\Libraries\Currency\Conversion\CurrencyApi;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * @test
 * @covers  App\Libraries\Currency\Conversion\CurrencyApi
 */
class CurrencyApiTest extends TestCase
{
    protected function setUp() :void
    {
        parent::setUp();
    }

    public function testCurrencyConversionWorking()
    {
        $converter = new CurrencyApi();

        $converted_amount = $converter->convert(100, 1, 2);

        $this->assertIsFloat($converted_amount);
    }

    public function testExchangeRate()
    {
        $converter = new CurrencyApi();

        $exchange_rate = $converter->exchangeRate(1, 2);

        $this->assertIsNumeric($exchange_rate);
    }

    public function testExchangeRateWithDate()
    {
        $date = Carbon::parse('2020-03-08');

        $converter = new CurrencyApi();

        $exchange_rate = $converter->exchangeRate(1, 2, $date);

        $this->assertIsNumeric($exchange_rate);
    }
}
