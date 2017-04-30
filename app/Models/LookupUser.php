<?php

namespace App\Models;

use Eloquent;

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

    public static function loadEmail($email)
    {
        if (! env('MULTI_DB_ENABLED')) {
            return;
        }

        $current = config('database.default');
        config(['database.default' => DB_NINJA_LOOKUP]);

        if ($lookupUser = static::whereEmail($email)->first()) {
            session(['SESSION_DB_SERVER' => $lookupUser->getDbServer()]);
        }

        config(['database.default' => $current]);

    }
}
