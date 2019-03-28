<?php

namespace App\Transformers;

use App\Models\Account;
use App\Models\User;

/**
 * @SWG\Definition(definition="User", @SWG\Xml(name="User"))
 */
class UserTransformer extends EntityTransformer
{
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
    ];

    /**
     * @var array
     */
    protected $availableIncludes = [
        'user_company',
    ];


    public function transform(User $user)
    {
        return [
            'id' => (int) $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
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

        $transformer = new UserCompanyTransformer($this->serializer);

        return $this->includeItem($user->user_company(), $transformer, CompanyUser::class);
    
    }

}
