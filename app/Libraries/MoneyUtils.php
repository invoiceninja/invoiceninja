<?php

namespace App\Libraries;

class MoneyUtilsInvalidArgumentException extends \Exception {};

/**
 * Class MoneyUtils
 * @package App\Libraries
 */
class MoneyUtils
{
    /**
     * @var bool this is to prevent repeating initialization by loadCurrencies method
     */
    protected static $initialized;

    /**
     * @var array holds currency code => exchange rate
     */
    protected static $rates = [];

    /**
     * @var string holds base currency code
     */
    protected static $baseCurency;

    /**
     * Loads currencies from cache, make $rates array and $baseCurrency
     * @throws \Exception
     */
    protected static function loadCurrencies()
    {
        if(self::$initialized) return;

        $currencies = cache('currencies');

        foreach ($currencies as $currency)
        {
            if(!self::$baseCurency && $currency->exchange_rate === 1.0000)
            {
                self::$baseCurency = $currency->code;
            }

            self::$rates[$currency->code] = $currency->exchange_rate;
        }

        self::$initialized = true;
    }

    /**
     * Currency conversion
     *
     * @param  $val  value to convert
     * @param  $from currency to convert from
     * @param  $to   currency to convert to
     * @throws \Exception
     * @throws MoneyUtilsInvalidArgumentException
     */
    public static function convert($val, $from, $to)
    {
        return $val * self::getRate($from, $to);
    }

    /**
     * Get exchange rate for `to` currency based on `from` currency
     *
     * @param $from
     * @param $to
     * @return float|int|mixed
     * @throws \Exception
     * @throws MoneyUtilsInvalidArgumentException
     */
    public static function getRate($from, $to)
    {
        self::loadCurrencies();

        if(!array_key_exists($from, self::$rates) || !array_key_exists($to, self::$rates))
        {
            throw new MoneyUtilsInvalidArgumentException('Invalid currency code $from or $to');
        }

        // if `from` currency is same as `base` currency, return the basic exchange rate for the `to` currency
        if($from === self::$baseCurency)
        {
            return self::$rates[$to];
        }

        // if `to` currency is same as `base` currency, return the basic inverse of the `from` currency
        if($to === self::$baseCurency)
        {
            return 1 / self::$rates[$from];
        }

        // Otherwise, return the `to` rate multiplied by the inverse of the `from` rate to get the
        // relative exchange rate between the two currencies
        return self::$rates[$to] * (1 / self::$rates[$from]);
    }
}