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

    public static function setServerByEmail($email)
    {
        if (! env('MULTI_DB_ENABLED')) {
            return;
        }

        $current = config('database.default');
        config(['database.default' => DB_NINJA_LOOKUP]);

        if ($lookupUser = static::whereEmail($email)->first()) {
            $server = $lookupUser->getDbServer();
            session(['SESSION_DB_SERVER' => $server]);
            config(['database.default' => $server]);

            if (! User::whereEmail($email)->first()) {
                abort('Lookedup user not found: ' . $email);
            }
        } else {
            config(['database.default' => $current]);
        }
    }
}
