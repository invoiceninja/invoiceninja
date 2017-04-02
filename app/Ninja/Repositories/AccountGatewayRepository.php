<?php

namespace App\Ninja\Repositories;

use DB;

class AccountGatewayRepository extends BaseRepository
{
    public function getClassName()
    {
        return 'App\Models\AccountGateway';
    }

    public function find($accountId)
    {
        $query = DB::table('account_gateways')
                    ->join('gateways', 'gateways.id', '=', 'account_gateways.gateway_id')
                    ->join('accounts', 'accounts.id', '=', 'account_gateways.account_id')
                    ->where('account_gateways.account_id', '=', $accountId)
                    ->whereNull('account_gateways.deleted_at');

        return $query->select(
            'account_gateways.id',
            'account_gateways.public_id',
            'gateways.name',
            'gateways.name as gateway',
            'account_gateways.deleted_at',
            'account_gateways.gateway_id',
            'accounts.gateway_fee_enabled');
    }
}
