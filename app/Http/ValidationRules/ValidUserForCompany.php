<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\ValidationRules;

use App\Libraries\MultiDB;
use App\Models\User;
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
        return MultiDB::checkUserAndCompanyCoExist($value, auth()->user()->company()->company_key);
    }

    /**
     * @return string
     */
    public function message()
    {
        return 'This user is unable to be attached to this company. Perhaps they have already registered a user on another account?';
        //return ctrans('texts.email_already_register');
    }
}
