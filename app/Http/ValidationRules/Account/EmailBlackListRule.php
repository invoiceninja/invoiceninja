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

namespace App\Http\ValidationRules\Account;

use App\Libraries\MultiDB;
use Illuminate\Contracts\Validation\Rule;

/**
 * Class EmailBlackListRule.
 */
class EmailBlackListRule implements Rule
{
    public array $blacklist = [

    ];

    /**
     * @param string $attribute
     * @param mixed $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        return ! in_array($value, $this->blacklist);
    }

    /**
     * @return string
     */
    public function message()
    {
        return 'This email address is blacklisted, if you think this is in error, please email contact@invoiceninja.com';
    }
}
