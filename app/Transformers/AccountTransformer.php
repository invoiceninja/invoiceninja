<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
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
     * @throws \Laracasts\Presenter\Exceptions\PresenterException
     *
     * @return array
     */
    public function transform(Account $account)
    {
        return [
            'id' => (string)$this->encodePrimaryKey($account->id),
            'default_url' => config('ninja.site_url'),
            'plan' => $account->getPlan(),
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
