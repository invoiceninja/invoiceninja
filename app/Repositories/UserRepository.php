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

namespace App\Repositories;

use App\DataMapper\CompanySettings;
use App\Events\User\UserWasArchived;
use App\Events\User\UserWasDeleted;
use App\Events\User\UserWasRestored;
use App\Models\CompanyUser;
use App\Models\User;
use App\Utils\Ninja;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * UserRepository.
 */
class UserRepository extends BaseRepository
{
    use MakesHash;

    /**
     * Saves the user and its contacts.
     *
     * @param array $data The data
     * @param \App\Models\User $user The user
     *
     * @param bool $unset_company_user
     * @return \App\Models\User user Object
     */
    public function save(array $data, User $user, $unset_company_user = false)
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

        //allow users to change only their passwords - not others!
        if (auth()->user()->id == $user->id && array_key_exists('password', $data) && isset($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        if (! $user->confirmation_code) {
            $user->confirmation_code = $this->createDbHash($company->db);
        }

        $user->account_id = $account->id;

        if (strlen($user->password) >= 1) {
            $user->has_password = true;
        }

        $user->save();

        if (isset($data['company_user'])) {
            $cu = CompanyUser::whereUserId($user->id)->whereCompanyId($company->id)->withTrashed()->first();

            /*No company user exists - attach the user*/
            if (! $cu) {
                $data['company_user']['account_id'] = $account->id;
                $data['company_user']['notifications'] = CompanySettings::notificationDefaults();
                $user->companies()->attach($company->id, $data['company_user']);
            } else {
                if (auth()->user()->isAdmin()) {
                    $cu->fill($data['company_user']);
                    $cu->restore();
                    $cu->tokens()->restore();
                    $cu->save();
                } else {
                    $cu->notifications = $data['company_user']['notifications'];
                    $cu->settings = $data['company_user']['settings'];
                    $cu->save();
                }
            }

            $user->with(['company_users' => function ($query) use ($company, $user) {
                $query->whereCompanyId($company->id)
                      ->whereUserId($user->id);
            }])->first();
        }
        $user->restore();

        return $user->fresh();
    }

    public function destroy(array $data, User $user)
    {
        if ($user->isOwner()) {
            return $user;
        }

        if (array_key_exists('company_user', $data)) {
            $this->forced_includes = 'company_users';

            $company = auth()->user()->company();

            $cu = CompanyUser::whereUserId($user->id)
                             ->whereCompanyId($company->id)
                             ->first();

            $cu->tokens()->forceDelete();
            $cu->forceDelete();
        }

        event(new UserWasDeleted($user, $company, Ninja::eventVars(auth()->user() ? auth()->user()->id : null)));

        $user->delete();

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

        event(new UserWasDeleted($user, auth()->user(), $company, Ninja::eventVars(auth()->user() ? auth()->user()->id : null)));

        $user->is_deleted = true;
        $user->save();
        $user->delete();

        return $user->fresh();
    }

    public function archive($user)
    {
        if ($user->trashed()) {
            return;
        }

        $user->delete();

        event(new UserWasArchived($user, auth()->user(), auth()->user()->company, Ninja::eventVars(auth()->user() ? auth()->user()->id : null)));
    }

    /**
     * @param $entity
     */
    public function restore($user)
    {
        if (! $user->trashed()) {
            return;
        }

        if (Ninja::isHosted()) {
            $count = User::where('account_id', auth()->user()->account_id)->count();
            if ($count >= auth()->user()->account->num_users) {
                return;
            }
        }

        $user->is_deleted = false;
        $user->save();
        $user->restore();

        $cu = CompanyUser::withTrashed()
                         ->where('user_id', $user->id)
                         ->where('company_id', auth()->user()->company()->id)
                         ->first();

        $cu->restore();

        event(new UserWasRestored($user, auth()->user(), auth()->user()->company, Ninja::eventVars(auth()->user() ? auth()->user()->id : null)));
    }
}
