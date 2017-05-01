<?php

namespace App\Models;

use Eloquent;

/**
 * Class ExpenseCategory.
 */
class LookupModel extends Eloquent
{
    /**
     * @var bool
     */
    public $timestamps = false;

    public function lookupAccount()
    {
        return $this->belongsTo('App\Models\LookupAccount');
    }

    public static function createNew($accountKey, $data)
    {
        if (! env('MULTI_DB_ENABLED')) {
            return;
        }

        $current = config('database.default');
        config(['database.default' => DB_NINJA_LOOKUP]);

        $lookupAccount = LookupAccount::whereAccountKey($accountKey)->first();

        if ($lookupAccount) {
            $data['lookup_account_id'] = $lookupAccount->id;
        } else {
            abort('Lookup account not found for ' . $accountKey);
        }

        static::create($data);

        config(['database.default' => $current]);
    }

    public static function setServerByField($field, $value)
    {
        if (! env('MULTI_DB_ENABLED')) {
            return;
        }

        $className = get_called_class();
        $className = str_replace('Lookup', '', $className);
        $key = sprintf('server:%s:%s:%s', $className, $field, $value);

        // check if we've cached this lookup
        if ($server = session($key)) {
            static::setDbServer($server);
            return;
        }

        $current = config('database.default');
        config(['database.default' => DB_NINJA_LOOKUP]);

        if ($lookupUser = static::where($field, '=', $value)->first()) {
            $server = $lookupUser->getDbServer();
            static::setDbServer($server);

            $entity = new $className();
            if (! $entity::where($field, '=', $value)->first()) {
                abort("Looked up {$className} not found: {$field} => {$value}");
            }

            session([$key => $server]);
        } else {
            config(['database.default' => $current]);
        }
    }

    public static function setDbServer($server)
    {
        session(['SESSION_USER_DB_SERVER' => $server]);
        config(['database.default' => $server]);
    }

    public function getDbServer()
    {
        return $this->lookupAccount->lookupCompany->dbServer->name;
    }
}
