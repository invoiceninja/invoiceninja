<?php namespace App\Ninja\Repositories;

use DB;
use Utils;
use Session;

class AccountGatewayRepository extends BaseRepository
{
    public function getClassName()
    {
        return 'App\Models\AccountGateway';
    }

    public function find($accountId)
    {
        return DB::table('account_gateways')
                    ->join('gateways', 'gateways.id', '=', 'account_gateways.gateway_id')
                    ->where('account_gateways.deleted_at', '=', null)
                    ->where('account_gateways.account_id', '=', $accountId)
                    ->select('account_gateways.public_id', 'gateways.name', 'account_gateways.deleted_at', 'account_gateways.gateway_id');
    }
}
