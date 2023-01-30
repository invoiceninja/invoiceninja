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

namespace App\Libraries\Currency\Conversion;

use App\Models\Currency;
use Illuminate\Support\Carbon;

class CurrencyApi implements CurrencyConversionInterface
{
    public function convert($amount, $from_currency_id, $to_currency_id, $date = null)
    {
        if (! $date) {
            $date = Carbon::now();
        }

        $from_currency = Currency::find($from_currency_id);

        $to_currency = Currency::find($to_currency_id);

        $usd_amount = $this->convertToUsd($amount, $from_currency);

        return $this->convertFromUsdToCurrency($usd_amount, $to_currency);
    }

    public function exchangeRate($from_currency_id, $to_currency_id, $date = null)
    {
        $from_currency = Currency::find($from_currency_id);

        $to_currency = Currency::find($to_currency_id);

        $usd_amount = $this->convertToUsd(1, $from_currency);

        return $this->convertFromUsdToCurrency($usd_amount, $to_currency);
    }

    /**
     * Converts a currency amount to USD.
     *
     * @param  float  $amount   amount
     * @param  object $currency currency object
     * @return float            USD Amount
     */
    private function convertToUsd($amount, $currency)
    {
        return $amount / $currency->exchange_rate;
    }

    /**
     * Converts USD to any other denomination.
     *
     * @param  float  $amount   amount
     * @param  object $currency destination currency
     * @return float            the converted amount
     */
    private function convertFromUsdToCurrency($amount, $currency)
    {
        return $amount * $currency->exchange_rate;
    }
}
