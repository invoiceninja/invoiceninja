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

namespace App\Http\ValidationRules;

use App\Libraries\MultiDB;
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
