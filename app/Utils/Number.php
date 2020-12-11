<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Utils;

use App\Models\Currency;

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
     * Formats a given value based on the clients currency.
     *
     * @param  float  $value    The number to be formatted
     * @param  object $currency The client currency object
     *
     * @return string           The formatted value
     */
    public static function formatValue($value, $currency) :string
    {
        $value = floatval($value);

        $thousand = $currency->thousand_separator;
        $decimal = $currency->decimal_separator;
        $precision = $currency->precision;

        return number_format($value, $precision, $decimal, $thousand);
    }

    /**
     * Formats a given value based on the clients currency
     * BACK to a float.
     *
     * @param string $value The formatted number to be converted back to float
     * @return float            The formatted value
     */
    public static function parseFloat($value)
    {
        // convert "," to "."
        $s = str_replace(',', '.', $value);

        // remove everything except numbers and dot "."
        $s = preg_replace("/[^0-9\.]/", '', $s);

        // remove all seperators from first part and keep the end
        $s = str_replace('.', '', substr($s, 0, -3)).substr($s, -3);

        // return float
        return (float) $s;
    }

    /**
     * Formats a given value based on the clients currency AND country.
     *
     * @param floatval $value The number to be formatted
     * @param $client
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
        if (isset($client->country->thousand_separator)) {
            $thousand = $client->country->thousand_separator;
        }

        if (isset($client->country->decimal_separator)) {
            $decimal = $client->country->decimal_separator;
        }

        if (isset($client->country->swap_currency_symbol)) {
            $swapSymbol = $client->country->swap_currency_symbol;
        }

        $value = number_format($value, $precision, $decimal, $thousand);
        $symbol = $currency->symbol;

        if ($client->getSetting('show_currency_code') === true && $currency->code == 'CHF') {
            return "{$code} {$value}";
        } elseif ($client->getSetting('show_currency_code') === true) {
            return "{$value} {$code}";
        } elseif ($swapSymbol) {
            return "{$value} ".trim($symbol);
        } elseif ($client->getSetting('show_currency_code') === false) {
            return "{$symbol}{$value}";
        } else {
            return self::formatValue($value, $currency);
        }
    }
}
