<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Utils\Traits;

use App\DataMapper\BaseSettings;
use App\DataMapper\CompanySettings;
use App\DataMapper\FeesAndLimits;

/**
 * Class CompanyGatewayFeesAndLimitsSaver
 * @package App\Utils\Traits
 */
trait CompanyGatewayFeesAndLimitsSaver
{
    public function validateFeesAndLimits($fees_and_limits)
    {
        $fees_and_limits = (object)$fees_and_limits;
        $casts = FeesAndLimits::$casts;

        foreach ($fees_and_limits as $fee_and_limit) {
            $fee_and_limit = (object)$fee_and_limit;

            foreach ($casts as $key => $value) {


                /* Handles unset settings or blank strings */
                if (!property_exists($fee_and_limit, $key) || is_null($fee_and_limit->{$key}) || !isset($fee_and_limit->{$key}) || $fee_and_limit->{$key} == '') {
                    continue;
                }
                
                /*Catch all filter */
                if (!$this->checkAttribute($value, $fee_and_limit->{$key})) {
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
                return is_float($value) || is_numeric(strval($value));
            case 'string':
                return method_exists($value, '__toString') || is_null($value) || is_string($value);
            case 'bool':
            case 'boolean':
                return is_bool($value) || (int) filter_var($value, FILTER_VALIDATE_BOOLEAN);
            case 'object':
                return is_object($value);
            case 'array':
                return is_array($value);
            case 'json':
                json_decode($string);
                    return (json_last_error() == JSON_ERROR_NONE);
            default:
                return false;
        }
    }

    public function cleanFeesAndLimits($fees_and_limits)
    {
        $new_arr = [];

        if(!is_array($fees_and_limits))
            return $new_arr;

        $fal = new FeesAndLimits;

        foreach ($fees_and_limits as $key => $value) {

            $key = $this->transformFeesAndLimitsKeys($key);

            $fal->{$key} = BaseSettings::castAttribute(FeesAndLimits::$casts[$key], $value);

        }

        return $fal;
    }

    private function transformFeesAndLimitsKeys($key)
    {
        switch ($key) {
            case 'tax_name1':
                return 'fee_tax_name1';
                break;
            case 'tax_name2':
                return 'fee_tax_name2';
                break;
           case 'tax_name3':
                return 'fee_tax_name3';
                break;
           case 'tax_rate1':
                return 'fee_tax_rate1';
                break;
           case 'tax_rate2':
                return 'fee_tax_rate2';
                break;
           case 'tax_rate3':
                return 'fee_tax_rate3';
                break;

            default:
                return $key;
                break;
        }

    }
}
