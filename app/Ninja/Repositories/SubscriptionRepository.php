<?php

namespace App\Ninja\Repositories;

use App\Models\Subscription;
use DB;

class SubscriptionRepository extends BaseRepository
{
    public function getClassName()
    {
        return 'App\Models\Subscription';
    }

    public function find($accountId)
    {
        $query = DB::table('subscriptions')
                  ->where('subscriptions.account_id', '=', $accountId)
                  ->whereNull('subscriptions.deleted_at')
                  ->select(
                    'subscriptions.public_id',
                    'subscriptions.target_url as target',
                    'subscriptions.event_id as event',
                    'subscriptions.deleted_at',
                    'subscriptions.format'
                );

        return $query;
    }
}
