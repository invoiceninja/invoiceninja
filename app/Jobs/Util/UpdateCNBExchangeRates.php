<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Jobs\Util;

use App\Libraries\MultiDB;
use App\Models\Currency;
use App\Models\Company;
use GuzzleHttp\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class UpdateCNBExchangeRates implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct()
    {
    }
    public function handle(): void
    {
        info('updating currencies from Czech National Bank');

        //Even if the CNB flag is enabled in the config, if the OpenExchange API key is set, we will not interfere 
        if (!empty(config('ninja.currency_converter_api_key')) || config('ninja.currency_converter_use_cnb') !== true) {
            return;
        }

        $cc_endpoint = 'https://api.cnb.cz/cnbapi/exrates/daily?lang=EN';
        $client = new Client();
        $response = $client->get($cc_endpoint);
        $currency_api = json_decode($response->getBody());

        //parse the JSON
        $rows = $currency_api->rates;
        $perCzk = [];
        foreach ($rows as $row) {
            // ensure we divide by the 'amount' (CNB may quote 100 JPY = 17.6 CZK)
            $perCzk[$row->currencyCode] = 1/$row->rate / $row->amount; //we need inverse values
        }
        // CZK is not in the feed, but per CZK is obviously 1
        $perCzk['CZK'] = 1.0;


        if (config('ninja.db.multi_db_enabled')) {
            foreach (MultiDB::$dbs as $db) {
                MultiDB::setDB($db);
                //Adjust the currency rates to our company rates
                //which currency is our base?
                //Based on my testing, the exchange rates are always based on first company and transformed as needed for subsequent companies, if any.
                $companyRate = $perCzk[Company::first()->currency()->code] ?? 'EUR'; //defaulting to EUR if unset.
                // now scale: per company currency = (per CZK) / (company per CZK)
                $perCompanyCurrency = [];
                foreach ($perCzk as $code => $rate) {
                    $perCompanyCurrency[$code] = $rate / $companyRate;
                }

                /* Update all currencies */
                Currency::all()->each(function ($currency) use ($perCompanyCurrency) {
                    if (array_key_exists($currency->code, $perCompanyCurrency)) {
                        $currency->exchange_rate = $perCompanyCurrency[$currency->code];
                        $currency->save();
                    }
                });

                /* Rebuild the cache */
                $currencies = Currency::orderBy('name')->get();

                Cache::forever('currencies', $currencies);
            }
        } else {
            //Adjust the currency rates to our company rates
            //which currency is our base?
            //Based on my testing, the exchange rates are always based on first company and transformed as needed for subsequent companies, if any.
            $companyRate = $perCzk[Company::first()->currency()->code] ?? 'EUR'; //defaulting to EUR if unset.
            // now scale: per company currency = (per CZK) / (company per CZK)
            $perCompanyCurrency = [];
            foreach ($perCzk as $code => $rate) {
                $perCompanyCurrency[$code] = $rate / $companyRate;
            }

            /* Update all currencies */
            Currency::all()->each(function ($currency) use ($perCompanyCurrency) {
                if (array_key_exists($currency->code, $perCompanyCurrency)) {
                    $currency->exchange_rate = $perCompanyCurrency[$currency->code];
                    $currency->save();
                }
            });

            /* Rebuild the cache */
            $currencies = Currency::orderBy('name')->get();

            Cache::forever('currencies', $currencies);
        }
    }
}
