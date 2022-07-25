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

namespace App\Utils\Traits;

use App\DataMapper\BaseSettings;
use App\DataMapper\FeesAndLimits;

/**
 * Class CompanyGatewayFeesAndLimitsSaver.
 */
trait CompanyGatewayFeesAndLimitsSaver
{
    public function validateFeesAndLimits($fees_and_limits)
    {
        $fees_and_limits = (object) $fees_and_limits;
        $casts = FeesAndLimits::$casts;

        foreach ($fees_and_limits as $fee_and_limit) {
            $fee_and_limit = (object) $fee_and_limit;

            foreach ($casts as $key => $value) {
                if ($value == 'float' && property_exists($fee_and_limit, $key)) {
                    $fee_and_limit->{$key} = floatval($fee_and_limit->{$key});
                }

                /* Handles unset settings or blank strings */
                if (! property_exists($fee_and_limit, $key) || is_null($fee_and_limit->{$key}) || ! isset($fee_and_limit->{$key}) || $fee_and_limit->{$key} == '') {
                    continue;
                }

                /*Catch all filter */
                if (! $this->checkAttribute($value, $fee_and_limit->{$key})) {
                    return [$key, $value];
                }
            }
        }

        return true;
    }

    /**
     * Type checks a object property.
     * @param  string $key   The type
     * @param  string $value The object property
     * @return bool        TRUE if the property is the expected type
     */
    private function checkAttribute($key, $value) :bool
    {
        switch ($key) {
            case 'int':
            case 'integer':
                return ctype_digit(strval($value));
            case 'real':
            case 'float':
            case 'double':
               return ! is_string($value) && (is_float($value) || is_numeric(strval($value)));
           //     return is_float($value) || is_numeric(strval($value));
            case 'string':
                return (is_string($value) && method_exists($value, '__toString')) || is_null($value) || is_string($value);
            case 'bool':
            case 'boolean':
                return is_bool($value) || (int) filter_var($value, FILTER_VALIDATE_BOOLEAN);
            case 'object':
                return is_object($value);
            case 'array':
                return is_array($value);
            case 'json':
                json_decode($value);

                    return json_last_error() == JSON_ERROR_NONE;
            default:
                return false;
        }
    }

    public function cleanFeesAndLimits($fees_and_limits)
    {
        $new_arr = [];

        foreach ($fees_and_limits as $key => $value) {
            $fal = new FeesAndLimits;
            // $fal->{$key} = $value;

            foreach ($value as $k => $v) {
                $fal->{$k} = $v;
                $fal->{$k} = BaseSettings::castAttribute(FeesAndLimits::$casts[$k], $v);
            }

            $new_arr[$key] = (array) $fal;
        }

        return $new_arr;
    }
}
