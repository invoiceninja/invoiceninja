<?php namespace App\Ninja\Transformers;

use App\Models\Account;
use App\Models\User;

class UserTransformer extends EntityTransformer
{
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