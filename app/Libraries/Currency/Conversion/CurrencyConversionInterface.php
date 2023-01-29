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

interface CurrencyConversionInterface
{
    public function convert($amount, $from_currency_id, $to_currency_id, $date = null);

    public function exchangeRate($from_currency_id, $to_currency_id, $date = null);
}
