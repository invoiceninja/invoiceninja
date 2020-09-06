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

namespace App\Repositories;

use App\DataMapper\CompanySettings;
use App\Events\User\UserWasDeleted;
use App\Factory\CompanyUserFactory;
use App\Models\CompanyUser;
use App\Models\User;
use App\Utils\Ninja;
use Illuminate\Http\Request;

/**
 * UserRepository.
 */
class UserRepository extends BaseRepository
{
    /**
     * Gets the class name.
     *
     * @return     string The class name.
     */
    public function getClassName()
    {
        return User::class;
    }

    /**
     * Saves the user and its contacts.
     *
     * @param      array                         $data    The data
     * @param      \App\Models\user              $user  The user
     *
     * @return     user|\App\Models\user|null  user Object
     */
    public function save(array $data, User $user, $is_migrating = false, $unset_company_user = false)
    {
        $details = $data;

        /*
         * Getting: SQLSTATE[42S22]: Column not found: 1054 Unknown column 'company_user'
         * because of User::unguard().
         * Solution. Unset company_user per request.
         */

        if ($unset_company_user) {
            unset($details['company_user']);
        }

        $company = auth()->user()->company();
        $account = $company->account;

        /* If hosted and Enterprise we need to increment the num_users field on the accounts table*/
        if (! $user->id && $account->isEnterpriseClient()) {
            $account->num_users++;
            $account->save();
        }

        $user->fill($details);
        $user->account_id = $account->id;
        $user->save();

        if (isset($data['company_user'])) {
            $cu = CompanyUser::whereUserId($user->id)->whereCompanyId($company->id)->withTrashed()->first();

            /*No company user exists - attach the user*/
            if (! $cu) {
                $data['company_user']['account_id'] = $account->id;
                $data['company_user']['notifications'] = CompanySettings::notificationDefaults();
                $data['company_user']['is_migrating'] = $is_migrating;
                $user->companies()->attach($company->id, $data['company_user']);
            } else {
                $cu->fill($data['company_user']);
                $cu->restore();
                $cu->tokens()->restore();
                $cu->is_migrating = true;
                $cu->save();
            }

            $user->with(['company_users' => function ($query) use ($company, $user) {
                $query->whereCompanyId($company->id)
                      ->whereUserId($user->id);
            }])->first();
        }
        $user->restore();

        return $user;
    }

    public function destroy(array $data, User $user)
    {
        if (array_key_exists('company_user', $data)) {
            $this->forced_includes = 'company_users';

            $company = auth()->user()->company();

            $cu = CompanyUser::whereUserId($user->id)
                             ->whereCompanyId($company->id)
                             ->first();

            $cu->tokens()->forceDelete();
            $cu->forceDelete();
        }

        $user->delete();

        event(new UserWasDeleted($user, $company, Ninja::eventVars()));

        return $user->fresh();
    }

    /*
     * Soft deletes the user and the company user
     */
    public function delete($user)
    {
        $company = auth()->user()->company();

        $cu = CompanyUser::whereUserId($user->id)
                         ->whereCompanyId($company->id)
                         ->first();

        if ($cu) {
            $cu->tokens()->delete();
            $cu->delete();
        }

        $user->is_deleted = true;
        $user->save();
        $user->delete();

        event(new UserWasDeleted($user, $company, Ninja::eventVars()));

        return $user->fresh();
    }
}
