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

namespace App\Http\ValidationRules\Ninja;

use App\Models\CompanyUser;
use App\Models\User;
use Illuminate\Contracts\Validation\Rule;

/**
 * Class CanAddUserRule.
 */
class CanAddUserRule implements Rule
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
        /** @var \App\Models\User $user */
        $user = auth()->user();

        /* If the user is active then we can add them to the company */
        if (User::where('email', request()->input('email'))->where('account_id', $user->account_id)->where('is_deleted', 0)->exists()) {
            return true;
        }

        /*
        Check that we have sufficient quota to allow this to happen

        @ 31-01-2024 - changed query to use email instead of user_id

        $count = CompanyUser::query()
                          ->where('company_user.account_id', $user->account_id)
                          ->join('users', 'users.id', '=', 'company_user.user_id')
                          ->whereNull('users.deleted_at')
                          ->whereNull('company_user.deleted_at')
                          ->distinct()
                          ->count('company_user.user_id');
        */

        $count = CompanyUser::query()
                        ->where("company_user.account_id", $user->account_id)
                        ->join("users", "users.id", "=", "company_user.user_id")
                        ->whereNull("users.deleted_at")
                        ->whereNull("company_user.deleted_at")
                        ->distinct()
                        ->count("users.email");

        return $count < $user->company()->account->num_users;
    }

    /**
     * @return string
     */
    public function message()
    {

        /** @var \App\Models\User $user */
        $user = auth()->user();

        return ctrans('texts.limit_users', ['limit' => $user->company()->account->num_users]);

    }
}
