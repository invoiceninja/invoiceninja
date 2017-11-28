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

    public function find($userId)
    {
        $query = DB::table('account_subscriptions')
                  ->where('account_subscriptions.user_id', '=', $userId)
                  ->whereNull('account_subscriptions.deleted_at');

        return $query->select('account_subscriptions.public_id', 'account_subscriptions.name', 'account_subscriptions.subscription', 'account_subscriptions.public_id', 'account_subscriptions.deleted_at');
    }
}
