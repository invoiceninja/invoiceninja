<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\ValidationRules\Ninja;

use App\Models\CompanyUser;
use App\Models\User;
use Illuminate\Contracts\Validation\Rule;

/**
 * Class CanAddUserRule.
 */
class CanRestoreUserRule implements Rule
{

    public function __construct()
    {
    }

    /**
     * @param string $attribute
     * @param mixed $value
     * @return bool
     */
    public function passes($attribute, $value)
    {

        $count = CompanyUser::query()
                            ->where('account_id', auth()->user()->account_id)
                            ->whereNull('deleted_at')
                            ->distinct()
                            ->count('user_id');


        return $count < auth()->user()->company()->account->num_users;

    }

    /**
     * @return string
     */
    public function message()
    {
        return ctrans('texts.limit_users', ['limit' => auth()->user()->company()->account->num_users]);
    }
}
