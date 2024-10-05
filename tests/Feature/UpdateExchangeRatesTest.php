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

namespace Tests\Feature;

use App\Jobs\Util\UpdateExchangeRates;
use App\Libraries\Currency\Conversion\CurrencyApi;
use App\Models\Currency;
use App\Utils\Traits\MakesHash;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * 
 *  App\Jobs\Util\UpdateExchangeRates
 */
class UpdateExchangeRatesTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        if(empty(config('ninja.currency_converter_api_key'))) {
            $this->markTestSkipped("no currency key set");
        }

    }

    public function testExchangeRate()
    {
        $cc_endpoint = sprintf('https://openexchangerates.org/api/latest.json?app_id=%s', config('ninja.currency_converter_api_key'));

        $client = new \GuzzleHttp\Client();
        $response = $client->get($cc_endpoint);

        $currency_api = json_decode($response->getBody());

        UpdateExchangeRates::dispatchSync();

        $gbp_currency = app('currencies')->first(function ($item) {
            return $item->id == 2;
        });

        $this->assertEquals($currency_api->rates->GBP, $gbp_currency->exchange_rate);

    }

    public function testExchangeRateConversion()
    {
        $usd = Currency::find(1);
        $gbp = Currency::find(2);

        $usd->exchange_rate = 1;
        $usd->save();

        $gbp->exchange_rate = 0.5;
        $gbp->save();

        $currency_api = new CurrencyApi();

        $convert_to_gbp = $currency_api->convert(10, 1, 2);

        $this->assertEquals($convert_to_gbp, 5);
    }

    public function testSyntheticExchangeRate()
    {
        $usd = Currency::find(1);
        $gbp = Currency::find(2);
        $aud = Currency::find(12);

        $usd->exchange_rate = 1;
        $usd->save();

        $gbp->exchange_rate = 0.5;
        $gbp->save();

        $aud->exchange_rate = 1.5;
        $aud->save();

        $currency_api = new CurrencyApi();

        $convert_to_aud = $currency_api->convert(10, 1, 12);

        $this->assertEquals($convert_to_aud, 15);

        $synthetic_exchange = $currency_api->exchangeRate($gbp->id, $aud->id);

        $this->assertEquals($synthetic_exchange, 3);
    }
}
