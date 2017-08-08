<?php

namespace App\Ninja\Transformers;

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
    public function transform(User $user)
    {
        return [
            'id' => (int) ($user->public_id + 1),
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'account_key' => $user->account->account_key,
            'updated_at' => $this->getTimestamp($user->updated_at),
            'deleted_at' => $this->getTimestamp($user->deleted_at),
            'phone' => $user->phone,
            //'username' => $user->username,
            'registered' => (bool) $user->registered,
            'confirmed' => (bool) $user->confirmed,
            'oauth_user_id' => $user->oauth_user_id,
            'oauth_provider_id' => $user->oauth_provider_id,
            'notify_sent' => (bool) $user->notify_sent,
            'notify_viewed' => (bool) $user->notify_viewed,
            'notify_paid' => (bool) $user->notify_paid,
            'notify_approved' => (bool) $user->notify_approved,
            'is_admin' => (bool) $user->is_admin,
            'permissions' => (int) $user->getOriginal('permissions'),
        ];
    }
}
