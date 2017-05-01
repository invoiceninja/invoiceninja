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
    ];

    public static function updateUser($accountKey, $userId, $email, $confirmationCode)
    {
        if (! env('MULTI_DB_ENABLED')) {
            return;
        }

        $current = config('database.default');
        config(['database.default' => DB_NINJA_LOOKUP]);

        $lookupAccount = LookupAccount::whereAccountKey($accountKey)
                            ->firstOrFail();

        $lookupUser = LookupUser::whereLookupAccountId($lookupAccount->id)
                            ->whereUserId($userId)
                            ->firstOrFail();

        $lookupUser->email = $email;
        $lookupUser->confirmation_code = $confirmationCode;
        $lookupUser->save();

        config(['database.default' => $current]);
    }

    public static function validateEmail($email, $user = false)
    {
        if (! env('MULTI_DB_ENABLED')) {
            return true;
        }

        $current = config('database.default');
        config(['database.default' => DB_NINJA_LOOKUP]);

        $lookupUser = LookupUser::whereEmail($email)->first();

        if ($user) {
            $lookupAccount = LookupAccount::whereAccountKey($user->account->account_key)->firstOrFail();
            $isValid = ! $lookupUser || ($lookupUser->lookup_account_id == $lookupAccount->id && $lookupUser->user_id == $user->id);
        } else {
            $isValid = ! $lookupUser;
        }

        config(['database.default' => $current]);

        return $isValid;
    }

}
