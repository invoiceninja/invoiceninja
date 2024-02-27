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
        if (config('ninja.testvars.travis')) {
            return true;
        }

        if (Ninja::isSelfHost()) {
            return auth()->user()->company()->account->companies->count() < 10;
        }

        return (auth()->user()->account->isPaid() || auth()->user()->account->isTrial()) && auth()->user()->company()->account->companies->count() < 10 ;
    }

    /**
     * @return string
     */
    public function message()
    {
        return ctrans('texts.company_limit_reached', ['limit' => Ninja::isSelfHost() ? 10 : auth()->user()->company()->account->hosted_company_count]);
    }
}
