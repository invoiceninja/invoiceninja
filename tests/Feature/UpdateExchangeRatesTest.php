<?php

namespace Tests\Feature;

use App\DataMapper\DefaultSettings;
use App\Jobs\Util\UpdateExchangeRates;
use App\Libraries\Currency\Conversion\CurrencyApi;
use App\Models\Account;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\User;
use App\Utils\Traits\MakesHash;
use AshAllenDesign\LaravelExchangeRates\Facades\ExchangeRate;
use Faker\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 * @covers App\Jobs\Util\UpdateExchangeRates
 */
class UpdateExchangeRatesTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockAccountData;

    public function setUp() :void
    {
        parent::setUp();

        $this->makeTestData();

        Session::start();

        $this->faker = \Faker\Factory::create();

        Model::reguard();
    }


    public function testExchangeRate()
    {
        $cc_endpoint = sprintf('https://openexchangerates.org/api/latest.json?app_id=%s', config('ninja.currency_converter_api_key'));

        $client = new \GuzzleHttp\Client();
        $response = $client->get($cc_endpoint);

        $currency_api = json_decode($response->getBody());

        UpdateExchangeRates::dispatchNow();

        $currencies = Cache::get('currencies');
        
        $gbp_currency = $currencies->filter(function ($item) {
            return $item->id == 2;
        })->first();

        $this->assertEquals($currency_api->rates->GBP, $gbp_currency->exchange_rate);

    }

}
