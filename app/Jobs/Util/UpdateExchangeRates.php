<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Jobs\Util;

use App\Libraries\MultiDB;
use App\Models\Currency;
use GuzzleHttp\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class UpdateExchangeRates implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct()
    {
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        info('updating currencies');

        if (empty(config('ninja.currency_converter_api_key'))) {
            return;
        }

        $cc_endpoint = sprintf('https://openexchangerates.org/api/latest.json?app_id=%s', config('ninja.currency_converter_api_key'));

        if (config('ninja.db.multi_db_enabled')) {
            foreach (MultiDB::$dbs as $db) {
                MultiDB::setDB($db);

                $client = new Client();
                $response = $client->get($cc_endpoint);

                $currency_api = json_decode($response->getBody());

                /* Update all currencies */
                Currency::all()->each(function ($currency) use ($currency_api) {
                    $currency->exchange_rate = $currency_api->rates->{$currency->code};
                    $currency->save();
                });

                /* Rebuild the cache */
                $currencies = Currency::orderBy('name')->get();

                Cache::forever('currencies', $currencies);
            }
        } else {
            $client = new Client();
            $response = $client->get($cc_endpoint);

            $currency_api = json_decode($response->getBody());

            /* Update all currencies */
            Currency::all()->each(function ($currency) use ($currency_api) {
                $currency->exchange_rate = $currency_api->rates->{$currency->code};
                $currency->save();
            });

            /* Rebuild the cache */
            $currencies = Currency::orderBy('name')->get();

            Cache::forever('currencies', $currencies);
        }
    }
}
