<?php namespace App\Ninja\Repositories;

use DB;
use Utils;
use Session;
use App\Models\BankAccount;
use App\Ninja\Repositories\BaseRepository;

class BankAccountRepository extends BaseRepository
{
    public function getClassName()
    {
        return 'App\Models\BankAccount';
    }

    public function find($accountId)
    {
        return DB::table('bank_accounts')
                    ->join('banks', 'banks.id', '=', 'bank_accounts.bank_id')
                    ->where('bank_accounts.deleted_at', '=', null)
                    ->where('bank_accounts.account_id', '=', $accountId)
                    ->select('bank_accounts.public_id', 'banks.name as bank_name', 'bank_accounts.deleted_at', 'banks.bank_library_id');
    }
}
