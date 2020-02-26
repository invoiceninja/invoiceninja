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
use App\Models\CompanyToken;
use App\Models\CompanyUser;
use App\Models\User;
use App\Transformers\AccountTransformer;
use App\Transformers\CompanyTokenTransformer;
use App\Transformers\UserTransformer;
use App\Transformers\CompanyTransformer;

class CompanyUserTransformer extends EntityTransformer
{
    
    /**
     * @var array
     */
    protected $defaultIncludes = [
    //     'account',
    //     'company',
    //     'user',
    //     'token'
     ];

    /**
     * @var array
     */
    protected $availableIncludes = [
        'user',
        'company',
        'token',
        'account',
    ];


    public function transform(CompanyUser $company_user)
    {
        return [
            // 'id' => $company_user->id,
            // 'account_id' => $company_user->account_id,
            // 'user_id' => $company_user->user_id,
            // 'company_id' => $company_user->company_id,
            'permissions' => $company_user->permissions ?: '',
            'settings' => $company_user->settings,
            'is_owner' => (bool) $company_user->is_owner,
            'is_admin' => (bool) $company_user->is_admin,
            'is_locked' => (bool) $company_user->is_locked,
            'updated_at' => (int)$company_user->updated_at,
            'archived_at' => (int)$company_user->deleted_at,
            
        ];
    }

    public function includeAccount(CompanyUser $company_user)
    {
        $transformer = new AccountTransformer($this->serializer);

        return $this->includeItem($company_user->account, $transformer, Account::class);
    }

    public function includeCompany(CompanyUser $company_user)
    {
        $transformer = new CompanyTransformer($this->serializer);

        return $this->includeItem($company_user->company, $transformer, Company::class);
    }

    public function includeUser(CompanyUser $company_user)
    {
        $transformer = new UserTransformer($this->serializer);

        return $this->includeItem($company_user->user, $transformer, User::class);
    }

    public function includeToken(CompanyUser $company_user)
    {
        $token = $company_user->tokens->where('company_id', $company_user->company_id)->where('user_id', $company_user->user_id)->first();

        $transformer = new CompanyTokenTransformer($this->serializer);

        return $this->includeItem($token, $transformer, CompanyToken::class);
    }
}
