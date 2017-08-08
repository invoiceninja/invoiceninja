<?php

namespace App\Models;

use Eloquent;
use App\Models\User;

/**
 * Class ExpenseCategory.
 */
class LookupUser extends LookupModel
{
    /**
     * @var array
     */
    protected $fillable = [
        'lookup_account_id',
        'email',
        'user_id',
        'confirmation_code',
        'oauth_user_key',
        'referral_code',
    ];

    public static function updateUser($accountKey, $user)
    {
        if (! env('MULTI_DB_ENABLED')) {
            return;
        }

        $current = config('database.default');
        config(['database.default' => DB_NINJA_LOOKUP]);

        $lookupAccount = LookupAccount::whereAccountKey($accountKey)
                            ->firstOrFail();

        $lookupUser = LookupUser::whereLookupAccountId($lookupAccount->id)
                            ->whereUserId($user->id)
                            ->firstOrFail();

        $lookupUser->email = $user->email;
        $lookupUser->confirmation_code = $user->confirmation_code ?: null;
        $lookupUser->oauth_user_key = ($user->oauth_provider_id && $user->oauth_user_id) ? ($user->oauth_provider_id . '-' . $user->oauth_user_id) : null;
        $lookupUser->referral_code = $user->referral_code;
        $lookupUser->save();

        config(['database.default' => $current]);
    }

    public static function validateField($field, $value, $user = false)
    {
        if (! env('MULTI_DB_ENABLED')) {
            return true;
        }

        $current = config('database.default');
        $accountKey = $user ? $user->account->account_key : false;

        config(['database.default' => DB_NINJA_LOOKUP]);

        $lookupUser = LookupUser::where($field, '=', $value)->first();

        if ($user) {
            $lookupAccount = LookupAccount::whereAccountKey($accountKey)->firstOrFail();
            $isValid = ! $lookupUser || ($lookupUser->lookup_account_id == $lookupAccount->id && $lookupUser->user_id == $user->id);
        } else {
            $isValid = ! $lookupUser;
        }

        config(['database.default' => $current]);

        return $isValid;
    }

}
