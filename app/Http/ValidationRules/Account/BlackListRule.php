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
 * Class BlackListRule.
 */
class BlackListRule implements Rule
{
    private array $blacklist = [
        'candassociates.com',
        'vusra.com',
        'fourthgenet.com',
        'arxxwalls.com',
        'superhostforumla.com'
    ];

    /**
     * @param string $attribute
     * @param mixed $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        $parts = explode('@', $value);

        if (is_array($parts)) {
            return ! in_array($parts[1], $this->blacklist);
        } else {
            return true;
        }
    }

    /**
     * @return string
     */
    public function message()
    {
        return 'This domain is blacklisted, if you think this is in error, please email contact@invoiceninja.com';
    }
}
