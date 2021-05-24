<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
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
        $current_db = config('database.default');

        $result = MultiDB::checkUserAndCompanyCoExist($value, auth()->user()->company()->company_key, auth()->user()->company()->id);


        MultiDB::setDb($current_db);

        return $result;
    }

    /**
     * @return string
     */
    public function message()
    {
        return ctrans('texts.user_not_associated_with_this_account');
    }
}
