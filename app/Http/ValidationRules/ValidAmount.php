<?php

/**
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\ValidationRules;

use Illuminate\Contracts\Validation\Rule;

/**
 * Class ValidAmount.
 */
class ValidAmount implements Rule
{
    /**
     * @param string $attribute
     * @param mixed $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        return is_numeric((string) $value);
        //return filter_var((string)$value, FILTER_VALIDATE_FLOAT);
        //        return preg_match('^(?=.)([+-]?([0-9]*)(\.([0-9]+))?)$^', (string)$value);
        // return trim($value, '-1234567890.,') === '';
    }

    /**
     * @return string
     */
    public function message()
    {
        return ctrans('texts.invalid_amount');
    }
}
