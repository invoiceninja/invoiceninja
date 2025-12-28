<?php

/**
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Libraries\Currency\Conversion;

interface CurrencyConversionInterface
{
    public function convert($amount, $from_currency_id, $to_currency_id, $date = null);

    public function exchangeRate($from_currency_id, $to_currency_id, $date = null);
}
