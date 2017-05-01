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

    public static function updateUser($accountKey, $userId, $email)
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
        $lookupUser->save();

        config(['database.default' => $current]);
    }

}
