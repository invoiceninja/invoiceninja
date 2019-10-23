<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2019. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Utils;

use App\Models\Currency;
use Illuminate\Support\Facades\Log;

/**
 * Class Number.
 */
class Number
{
    /**
     * @param float $value
     * @param int   $precision
     *
     * @return float
     */
    public static function roundValue(float $value, int $precision = 2) : float
    {
        return round($value, $precision, PHP_ROUND_HALF_UP);
    }

    /**
     * Formats a given value based on the clients currency
     *     
     * @param  float $value    The number to be formatted
     * @param  object $currency The client currency object
     * 
     * @return float           The formatted value
     */
    public static function formatValue($value, $currency) : float
    {
        $value = floatval($value);

        $thousand = $currency->thousand_separator;
        $decimal = $currency->decimal_separator;
        $precision = $currency->precision;

        return number_format($value, $precision, $decimal, $thousand);

    }

    /**
     * Formats a given value based on the clients currency AND country
     *     
     * @param  floatval $value    The number to be formatted
     * @param  object $currency The client currency object
     * @param  object $country The client country
     * 
     * @return string           The formatted value
     */
    public static function formatMoney($value, $client) :string
    {
        $currency = $client->currency();

        $thousand = $currency->thousand_separator;
        $decimal = $currency->decimal_separator;
        $precision = $currency->precision;
        $code = $currency->code;
        $swapSymbol = $currency->swap_currency_symbol;

            /* Country settings override client settings */
            if ($client->country->thousand_separator) 
                $thousand = $client->country->thousand_separator;
            
            if ($client->country->decimal_separator) 
                $decimal = $client->country->decimal_separator;
            
        $value = number_format($value, $precision, $decimal, $thousand);
        $symbol = $currency->symbol;
        
        if ($client->getSetting('show_currency_code') === true) {
            return "{$value} {$code}";
        } elseif ($swapSymbol) {
            return "{$value} " . trim($symbol);
        } elseif ($client->getSetting('show_currency_code') === false) {
            return "{$symbol}{$value}";
        } else {
            return self::formatValue($value, $currency);
        }
    }
}
