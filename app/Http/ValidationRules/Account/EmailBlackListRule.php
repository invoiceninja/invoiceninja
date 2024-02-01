<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\ValidationRules\Account;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Class EmailBlackListRule.
 */
class EmailBlackListRule implements ValidationRule
{
    public array $blacklist = [
        'noddy@invoiceninja.com',
    ];


    public function validate(string $attribute, mixed $value, Closure $fail): void
    {

        if (in_array($value, $this->blacklist)) {
            $fail('This email address is blacklisted, if you think this is in error, please email contact@invoiceninja.com');
        }

    }

}
