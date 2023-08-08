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

namespace App\Http\ValidationRules;

use App\Libraries\MultiDB;
use Illuminate\Contracts\Validation\Rule;

/**
 * Class ValidUserForCompany.
 */
class ValidUserForCompany implements Rule
{
    /**
     * @param string $attribute
     * @param mixed $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        /** @var \App\Models\User auth()->user() */
        $user = auth()->user();

        return MultiDB::checkUserAndCompanyCoExist($value, $user->company()->company_key);
    }

    /**
     * @return string
     */
    public function message()
    {
        return ctrans('texts.user_not_associated_with_this_account');
    }
}
