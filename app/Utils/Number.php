<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Utils;

use App\Models\Company;
use App\Models\Currency;
use App\Models\Vendor;

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
     * Formats a given value based on the clients currency.
     *
     * @param  float  $value    The number to be formatted
     * @param  object $currency The client currency object
     *
     * @return string           The formatted value
     */
    public static function formatValueNoTrailingZeroes($value, $currency) :string
    {
        $value = floatval($value);

        $thousand = $currency->thousand_separator;
        $decimal = $currency->decimal_separator;
        $precision = $currency->precision;

        $precision = 10;

        return rtrim(rtrim(number_format($value, $precision, $decimal, $thousand), "0"),$decimal);
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

        if($s < 1)
            return (float)$s;

        // remove all seperators from first part and keep the end
        $s = str_replace('.', '', substr($s, 0, -3)).substr($s, -3);

        // return float
        return (float) $s;
    }

    public static function parseStringFloat($value)
    {
        $value = preg_replace('/[^0-9-.]+/', '', $value);

        // check for comma as decimal separator
        if (preg_match('/,[\d]{1,2}$/', $value)) {
            $value = str_replace(',', '.', $value);
        }

        $value = preg_replace('/[^0-9\.\-]/', '', $value);

        return floatval($value);
    }

    /**
     * Formats a given value based on the clients currency AND country.
     *
     * @param floatval $value The number to be formatted
     * @param $entity
     * @return string           The formatted value
     */
    public static function formatMoney($value, $entity) :string
    {

        $currency = $entity->currency();

        $thousand = $currency->thousand_separator;
        $decimal = $currency->decimal_separator;
        $precision = $currency->precision;
        $code = $currency->code;
        $swapSymbol = $currency->swap_currency_symbol;

        // App\Models\Client::country() returns instance of BelongsTo.
        // App\Models\Company::country() returns record for the country, that's why we check for the instance.

        if ($entity instanceof Company) {
            $country = $entity->country();
        } else {
            $country = $entity->country;
        }

        /* Country settings override client settings */
        if (isset($country->thousand_separator) && strlen($country->thousand_separator) >= 1) {
            $thousand = $country->thousand_separator;
        }

        if (isset($country->decimal_separator) && strlen($country->decimal_separator) >= 1) {
            $decimal = $country->decimal_separator;
        }

        if (isset($country->swap_currency_symbol) && strlen($country->swap_currency_symbol) >= 1) {
            $swapSymbol = $country->swap_currency_symbol;
        }

        $value = number_format($value, $precision, $decimal, $thousand);
        $symbol = $currency->symbol;

        if ($entity->getSetting('show_currency_code') === true && $currency->code == 'CHF') {
            return "{$code} {$value}";
        } elseif ($entity->getSetting('show_currency_code') === true) {
            return "{$value} {$code}";
        } elseif ($swapSymbol) {
            return "{$value} ".trim($symbol);
        } elseif ($entity->getSetting('show_currency_code') === false) {
            return "{$symbol}{$value}";
        } else {
            return self::formatValue($value, $currency);
        }
    }

/**
     * Formats a given value based on the clients currency AND country.
     *
     * @param floatval $value The number to be formatted
     * @param $entity
     * @return string           The formatted value
     */
    public static function formatMoneyNoRounding($value, $entity) :string
    {
        $currency = $entity->currency();

        $thousand = $currency->thousand_separator;
        $decimal = $currency->decimal_separator;
        $precision = $currency->precision;
        $code = $currency->code;
        $swapSymbol = $currency->swap_currency_symbol;

        if ($entity instanceof Company) {
            $country = $entity->country();
        } else {
            $country = $entity->country;
        }

        /* Country settings override client settings */
        if (isset($country->thousand_separator) && strlen($country->thousand_separator) >= 1) {
            $thousand = $country->thousand_separator;
        }

        if (isset($country->decimal_separator) && strlen($country->decimal_separator) >= 1) {
            $decimal = $country->decimal_separator;
        }

        if (isset($country->swap_currency_symbol) && strlen($country->swap_currency_symbol) >= 1) {
            $swapSymbol = $country->swap_currency_symbol;
        }

        /* 08-01-2022 allow increased precision for unit price*/
        $v = rtrim(sprintf('%f', $value),"0");
        // $precision = strlen(substr(strrchr($v, $decimal), 1));
        
        if($v<1)
            $precision = strlen($v) - strrpos($v, '.') - 1;

        // if($precision == 1)
        //     $precision = 2;

        $value = number_format($v, $precision, $decimal, $thousand);
        $symbol = $currency->symbol;

        if ($entity->getSetting('show_currency_code') === true && $currency->code == 'CHF') {
            return "{$code} {$value}";
        } elseif ($entity->getSetting('show_currency_code') === true) {
            return "{$value} {$code}";
        } elseif ($swapSymbol) {
            return "{$value} ".trim($symbol);
        } elseif ($entity->getSetting('show_currency_code') === false) {
            return "{$symbol}{$value}";
        } else {
            return self::formatValue($value, $currency);
        }
    }

}
