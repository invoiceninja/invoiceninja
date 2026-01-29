<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Repositories;

use App\Models\Task;
use App\Models\User;
use App\Utils\Ninja;
use App\Models\Quote;
use App\Models\Client;
use App\Models\Credit;
use App\Models\Vendor;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Project;
use App\Models\CompanyUser;
use Illuminate\Http\Request;
use App\Models\PurchaseOrder;
use App\Models\RecurringQuote;
use App\Utils\Traits\MakesHash;
use App\Models\RecurringExpense;
use App\Models\RecurringInvoice;
use Illuminate\Support\Facades\DB;
use App\DataMapper\CompanySettings;
use App\Events\User\UserWasDeleted;
use App\Events\User\UserWasArchived;
use App\Events\User\UserWasRestored;
use App\Repositories\BaseRepository;
use Illuminate\Support\Facades\Hash;
use App\Jobs\Company\CreateCompanyToken;

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
    public function save(array $data, User $user, $unset_company_user = false, $is_migrating = false)
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

        if (request()->has('validated_phone')) {
            $details['phone'] = request()->input('validated_phone');
            $user->verified_phone_number = false;
        }

        $user->fill($details);

        //allow users to change only their passwords - not others!
        if (auth()->user()->id == $user->id && array_key_exists('password', $data) && isset($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        if (! $user->confirmation_code && !$is_migrating) {
            $user->confirmation_code = $this->createDbHash($company->db);
        }

        //@18-10-2024 - ensure no cross account linkage.
        if (is_numeric($user->account_id) && $user->account_id != $account->id) {
            throw new \Illuminate\Auth\Access\AuthorizationException("Illegal operation encountered for {$user->hashed_id}", 401);
        }

        $user->account_id = $account->id;//@todo we should never change the account_id if it is set at this point.

        if (strlen($user->password ?? '') >= 1) {
            $user->has_password = true;
        }

        $user->save();

        if (isset($data['company_user'])) {
            $cu = CompanyUser::query()->whereUserId($user->id)->whereCompanyId($company->id)->withTrashed()->first();

            /*No company user exists - attach the user*/
            if (! $cu) {
                $data['company_user']['account_id'] = $account->id;
                $data['company_user']['notifications'] = isset($data['company_user']['notifications']['email']) ? $data['company_user']['notifications'] : CompanySettings::notificationDefaults();
                $user->companies()->attach($company->id, $data['company_user']);
            } else {
                if (auth()->user()->isAdmin()) {
                    $cu->fill($data['company_user']);
                    $cu->restore();
                    $cu->tokens()->restore();
                    $cu->save();

                    //05-08-2022
                    if ($cu->tokens()->count() == 0) {
                        (new CreateCompanyToken($cu->company, $cu->user, 'restored_user'))->handle();
                    }
                } else {
                    $cu->notifications = $data['company_user']['notifications'] ?? '';
                    $cu->settings = $data['company_user']['settings'] ?? '';
                    $cu->save();
                }
            }

            $user->with(['company_users' => function ($query) use ($company, $user) {
                $query->whereCompanyId($company->id)
                      ->whereUserId($user->id);
            }])->first();
        }
        $user->restore();

        $this->verifyCorrectCompanySizeForPermissions($user);

        return $user->fresh();
    }

    public function destroy(array $data, User $user)
    {
        if ($user->hasOwnerFlag()) {
            return $user;
        }

        if (array_key_exists('company_user', $data)) {
            $this->forced_includes = 'company_users';

            $company = auth()->user()->company();

            $cu = CompanyUser::query()->whereUserId($user->id)
                             ->whereCompanyId($company->id)
                             ->first();

            $cu->tokens()->forceDelete();
            $cu->forceDelete();
        }

        event(new UserWasDeleted($user, auth()->user(), auth()->user()->company(), Ninja::eventVars(auth()->user() ? auth()->user()->id : null)));

        $user->delete();

        return $user->fresh();
    }

    /*
     * Soft deletes the user and the company user
     */
    public function delete($user)
    {
        $company = auth()->user()->company();

        $cu = CompanyUser::query()->whereUserId($user->id)
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
            $count = User::query()->where('account_id', auth()->user()->account_id)->count();
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
        $cu->tokens()->restore();

        event(new UserWasRestored($user, auth()->user(), auth()->user()->company, Ninja::eventVars(auth()->user() ? auth()->user()->id : null)));
    }


    /**
     * If we have multiple users in the system,
     * and there are some that are not admins,
     * we force all companies to large to ensure
     * the queries are appropriate for all users
     *
     * @param  User   $user
     * @return void
     */
    private function verifyCorrectCompanySizeForPermissions(User $user): void
    {
        if (Ninja::isSelfHost() || (Ninja::isHosted() && $user->account->isEnterpriseClient())) {
            $user->account()
               ->whereHas('companies', function ($query) {
                   $query->where('is_large', 0);
               })
               ->whereHas('company_users', function ($query) {
                   $query->where('is_admin', 0);
               })
               ->cursor()->each(function ($account) {
                   $account->companies()->update(['is_large' => true]);
               });
        }
    }
    
    /**
     * purge a user and all of their data across
     * all companies and accounts.
     *
     * @param  User $user
     * @param  User $new_owner_user
     * @return void
     */
    public function purge(User $user, User $new_owner_user): void
    {

        \DB::transaction(function () use ($user, $new_owner_user) {
            
            // Relations to transfer user_id to new owner
            $allRelations = [
                'activities', 'bank_integrations', 'bank_transaction_rules',
                'bank_transactions', 'client_contacts', 'company_gateways',
                'company_ledgers', 'company_tokens', 'credit_invitations',
                'designs', 'expense_categories', 'group_settings',
                'invoice_invitations', 'locations', 'payment_terms',
                'quote_invitations', 'purchase_order_invitations',
                'recurring_invoice_invitations', 'recurring_quote_invitations',
                'schedules', 'system_logs', 'task_statuses', 'tax_rates',
                'vendor_contacts', 'webhooks',
                // Models that also have assigned_user_id
                'clients', 'invoices', 'credits', 'quotes', 'payments',
                'expenses', 'tasks', 'projects', 'vendors', 'products',
                'purchase_orders', 'recurring_invoices', 'recurring_expenses',
                'recurring_quotes',
            ];

            foreach ($allRelations as $relation) {
                $user->{$relation}()->update(['user_id' => $new_owner_user->id]);
            }

            // Models with both user_id and assigned_user_id
            $modelsWithAssignedUser = [
                Client::class, Invoice::class, Credit::class, Quote::class,
                Payment::class, Expense::class, Task::class, Project::class,
                Vendor::class, Product::class, PurchaseOrder::class,
                RecurringInvoice::class, RecurringExpense::class, RecurringQuote::class,
            ];

            foreach ($modelsWithAssignedUser as $model) {
                // Null out assigned_user_id
                $model::withTrashed()
                    ->where('assigned_user_id', $user->id)
                    ->update(['assigned_user_id' => null]);
            }

            $user->forceDelete();
        });

    }
}
