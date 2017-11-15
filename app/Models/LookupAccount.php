<?php

namespace App\Models;

use Eloquent;

/**
 * Class ExpenseCategory.
 */
class LookupAccount extends LookupModel
{
    /**
     * @var array
     */
    protected $fillable = [
        'lookup_company_id',
        'account_key',
    ];

    public function lookupCompany()
    {
        return $this->belongsTo('App\Models\LookupCompany');
    }

    public static function createAccount($accountKey, $companyId)
    {
        if (! env('MULTI_DB_ENABLED')) {
            return;
        }

        $current = config('database.default');
        config(['database.default' => DB_NINJA_LOOKUP]);

        $server = DbServer::whereName($current)->firstOrFail();
        $lookupCompany = LookupCompany::whereDbServerId($server->id)
                            ->whereCompanyId($companyId)->first();

        if (! $lookupCompany) {
            $lookupCompany = LookupCompany::create([
                'db_server_id' => $server->id,
                'company_id' => $companyId,
            ]);
        }

        LookupAccount::create([
            'lookup_company_id' => $lookupCompany->id,
            'account_key' => $accountKey,
        ]);

        static::setDbServer($current);
    }

    public function getDbServer()
    {
        return $this->lookupCompany->dbServer->name;
    }

    public static function updateAccount($accountKey, $account)
    {
        if (! env('MULTI_DB_ENABLED')) {
            return;
        }

        $current = config('database.default');
        config(['database.default' => DB_NINJA_LOOKUP]);

        $lookupAccount = LookupAccount::whereAccountKey($accountKey)
                            ->firstOrFail();

        $lookupAccount->subdomain = $account->subdomain ?: null;
        $lookupAccount->save();

        config(['database.default' => $current]);
    }

    public static function validateField($field, $value, $account = false)
    {
        if (! env('MULTI_DB_ENABLED')) {
            return true;
        }

        $current = config('database.default');

        config(['database.default' => DB_NINJA_LOOKUP]);

        $lookupAccount = LookupAccount::where($field, '=', $value)->first();

        if ($account) {
            $isValid = ! $lookupAccount || ($lookupAccount->account_key == $account->account_key);
        } else {
            $isValid = ! $lookupAccount;
        }

        config(['database.default' => $current]);

        return $isValid;
    }
}
