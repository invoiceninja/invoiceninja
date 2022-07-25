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

namespace App\Http\ValidationRules\Company;

use App\Utils\Ninja;
use Illuminate\Contracts\Validation\Rule;

/**
 * Class ValidCompanyQuantity.
 */
class ValidCompanyQuantity implements Rule
{
    /**
     * @param string $attribute
     * @param mixed $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        if (Ninja::isSelfHost()) {
            return auth()->user()->company()->account->companies->count() < 10;
        }

        return auth()->user()->company()->account->companies->count() < auth()->user()->company()->account->hosted_company_count;
    }

    /**
     * @return string
     */
    public function message()
    {
        return ctrans('texts.company_limit_reached');
    }
}
