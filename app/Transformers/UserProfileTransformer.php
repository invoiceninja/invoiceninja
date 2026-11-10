<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Transformers;

use App\Models\User;
use App\Utils\Traits\MakesHash;

class UserProfileTransformer extends EntityTransformer
{
    use MakesHash;

    /**
     * @var array
     */
    protected array $defaultIncludes = [
    ];

    /**
     * @var array
     */
    protected array $availableIncludes = [
    ];

    public function transform(User $user)
    {
    
        return [
            'id' => $this->encodePrimaryKey($user->id),
            'first_name' => $user->first_name ?: '',
            'last_name' => $user->last_name ?: '',
            'email' => $user->email ?: '',
            'last_login' => 0,
            'created_at' => (int) $user->created_at,
            'updated_at' => (int) $user->updated_at,
            'archived_at' => (int) $user->deleted_at,
            'is_deleted' => (bool) $user->is_deleted,
            'phone' => $user->phone ?: '',
            'email_verified_at' => $user->getEmailVerifiedAt(),
            'signature' => $user->signature ?: '',
            'custom_value1' => $user->custom_value1 ?: '',
            'custom_value2' => $user->custom_value2 ?: '',
            'custom_value3' => $user->custom_value3 ?: '',
            'custom_value4' => $user->custom_value4 ?: '',
            'oauth_provider_id' => '',
            'last_confirmed_email_address' => '',
            'google_2fa_secret' => (bool) false,
            'passkey_enabled' => (bool) false,
            'passkey_count' => 0,
            'has_password' => false,
            'oauth_user_token' => '***',
            'verified_phone_number' => false,
            'language_id' => '',
            'user_logged_in_notification' => false,
            'referral_code' => '',
            'referral_meta' => (object) [],
            'settings' => (object) [],
        ];
    }

}
