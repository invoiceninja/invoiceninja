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

namespace App\Transformers;

use App\Models\Account;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Payment;
use App\Models\User;
use App\Transformers\CompanyTransformer;
use App\Transformers\CompanyUserTransformer;
use App\Transformers\UserTransformer;
use App\Utils\Traits\MakesHash;
use Laracasts\Presenter\Exceptions\PresenterException;

/**
 * Class AccountTransformer.
 */
class AccountTransformer extends EntityTransformer
{
    use MakesHash;

    /**
     * @var array
     */
    protected $defaultIncludes = [
        //'default_company',
        //'user',
        //'company_users'
    ];

    /**
     * @var array
     */
    protected $availableIncludes = [
        'default_company',
        'company_users',
        'companies',
    ];

    /**
     * @param Account $account
     *
     *
     * @return array
     */
    public function transform(Account $account)
    {
        return [
            'id' => (string) $this->encodePrimaryKey($account->id),
            'default_url' => config('ninja.app_url'),
            'plan' => $account->getPlan(),
            'plan_term' => (string) $account->plan_terms,
            'plan_started' => (string) $account->plan_started,
            'plan_paid' => (string) $account->plan_paid,
            'plan_expires' => (string) $account->plan_expires,
            'user_agent' => (string) $account->user_agent,
            'payment_id' => (string) $account->payment_id,
            'trial_started' => (string) $account->trial_started,
            'trial_plan' => (string) $account->trial_plan,
            'plan_price' => (float) $account->plan_price,
            'num_users' => (int) $account->num_users,
            'utm_source' => (string) $account->utm_source,
            'utm_medium' => (string) $account->utm_medium,
            'utm_content' => (string) $account->utm_content,
            'utm_term' => (string) $account->utm_term,
            'referral_code' => (string) $account->referral_code,
            'latest_version' => (string) $account->latest_version,
            'current_version' => (string) config('ninja.app_version'),
            'updated_at' => (int) $account->updated_at,
            'archived_at' => (int) $account->deleted_at,
            'report_errors' => (bool) $account->report_errors,
        ];
    }

    public function includeCompanyUsers(Account $account)
    {
        $transformer = new CompanyUserTransformer($this->serializer);

        return $this->includeCollection($account->company_users, $transformer, CompanyUser::class);
    }

    public function includeDefaultCompany(Account $account)
    {
        $transformer = new CompanyTransformer($this->serializer);

        return $this->includeItem($account->default_company, $transformer, Company::class);
    }

    public function includeUser(Account $account)
    {
        $transformer = new UserTransformer($this->serializer);

        return $this->includeItem(auth()->user(), $transformer, User::class);

//        return $this->includeItem($account->default_company->owner(), $transformer, User::class);
    }
}
