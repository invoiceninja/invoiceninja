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

use App\Models\Account;
use App\Models\Company;
use App\Models\CompanyToken;
use App\Models\CompanyUser;
use App\Models\User;
use App\Transformers\CompanyTokenTransformer;
use App\Transformers\CompanyTransformer;
use App\Transformers\CompanyUserTransformer;
use App\Utils\Traits\MakesHash;

/**
 * @SWG\Definition(definition="User", @SWG\Xml(name="User"))
 */
class UserTransformer extends EntityTransformer
{
    use MakesHash;
    /**
     * @SWG\Property(property="id", type="integer", example=1, readOnly=true)
     * @SWG\Property(property="first_name", type="string", example="John")
     * @SWG\Property(property="last_name", type="string", example="Doe")
     * @SWG\Property(property="email", type="string", example="johndoe@isp.com")
     * @SWG\Property(property="account_key", type="string", example="123456")
     * @SWG\Property(property="updated_at", type="integer", example=1451160233, readOnly=true)
     * @SWG\Property(property="deleted_at", type="integer", example=1451160233, readOnly=true)
     * @SWG\Property(property="phone", type="string", example="(212) 555-1212")
     * @SWG\Property(property="registered", type="boolean", example=false)
     * @SWG\Property(property="confirmed", type="boolean", example=false)
     * @SWG\Property(property="oauth_user_id", type="integer", example=1)
     * @SWG\Property(property="oauth_provider_id", type="integer", example=1)
     * @SWG\Property(property="notify_sent", type="boolean", example=false)
     * @SWG\Property(property="notify_viewed", type="boolean", example=false)
     * @SWG\Property(property="notify_paid", type="boolean", example=false)
     * @SWG\Property(property="notify_approved", type="boolean", example=false)
     * @SWG\Property(property="is_admin", type="boolean", example=false)
     * @SWG\Property(property="permissions", type="integer", example=1)
     */
    
    /**
     * @var array
     */
    protected $defaultIncludes = [
    //    'company_token',
       'token',
    ];

    /**
     * @var array
     */
    protected $availableIncludes = [
        'companies',
    ];


    public function transform(User $user)
    {
        return [
            'id' => $this->encodePrimaryKey($user->id),
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'last_login' => $user->last_login,
            'updated_at' => $user->updated_at,
            'deleted_at' => $user->deleted_at,
            'phone' => $user->phone,
            'email_verified_at' => $user->email_verified_at,
            'oauth_user_id' => $user->oauth_user_id,
            'oauth_provider_id' => $user->oauth_provider_id,
            'signature' => $user->signature,
        ];
    }

    public function includeUserCompany(User $user)
    {
        //cannot use this here as it will fail retrieving the company as we depend on the token in the header which may not be present for this request
        //$transformer = new CompanyUserTransformer($this->serializer);

        //return $this->includeItem($user->user_company(), $transformer, CompanyUser::class);
    
    }

    public function includeCompanies(User $user)
    {

        $transformer = new CompanyTransformer($this->serializer);

        return $this->includeCollection($user->companies, $transformer, Company::class);

    }

    public function includeToken(User $user)
    {

        $transformer = new CompanyTokenTransformer($this->serializer);

        return $this->includeItem($user->token, $transformer, CompanyToken::class);

    }

    public function includeCompanyTokens(User $user)
    {

        $transformer = new CompanyTokenTransformer($this->serializer);

        return $this->includeCollection($user->tokens, $transformer, CompanyToken::class);

    }
}
