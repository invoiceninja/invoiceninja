<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2019. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Transformers;

use App\Models\Company;
use App\Models\CompanyToken;
use App\Models\CompanyUser;
use App\Models\User;
use App\Transformers\CompanyTokenTransformer;

class CompanyUserTransformer extends EntityTransformer
{
    
    /**
     * @var array
     */
    protected $defaultIncludes = [
        'company',
        'user',
        'token'
    ];

    /**
     * @var array
     */
    protected $availableIncludes = [
        'user',
        'company',
        'token'
    ];


    public function transform(CompanyUser $company_user)
    {
        return [
            'permissions' => $company_user->permissions,
            'settings' => $company_user->settings,
            'is_owner' => (bool) $company_user->is_owner,
            'is_admin' => (bool) $company_user->is_admin,
            'is_locked' => (bool) $company_user->is_locked,
            'updated_at' => $company_user->updated_at,
            'deleted_at' => $company_user->deleted_at,
        ];
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

        $transformer = new CompanyTokenTransformer($this->serializer);

        return $this->includeItem($company_user->token, $transformer, CompanyToken::class);

    }

}
