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
use App\Models\CompanyUser;
use App\Models\User;

/**
 * @SWG\Definition(definition="CompanyUser", @SWG\Xml(name="CompanyUser"))
 */
class CompanyUserTransformer extends EntityTransformer
{
    
    /**
     * @var array
     */
    protected $defaultIncludes = [
    ];

    /**
     * @var array
     */
    protected $availableIncludes = [
        'user',
        'company'
    ];


    public function transform(CompanyUser $company_user)
    {
        return [
            'id' => (int) $company_user->id,
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

}
