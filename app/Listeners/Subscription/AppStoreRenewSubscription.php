<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Listeners\Subscription;

use App\Libraries\MultiDB;
use App\Models\Account;
use Illuminate\Contracts\Queue\ShouldQueue;
use Imdhemy\Purchases\Events\AppStore\DidRenew;

class AppStoreRenewSubscription implements ShouldQueue
{
    /**
     * Create the event listener.
     *
     * @param ActivityRepository $activity_repo
     */
    public function __construct()
    {
    }

    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle(DidRenew $event)
    {
        
        $inapp_transaction_id = $event->getSubscriptionId(); //$subscription_id
 
        MultiDB::findAndSetDbByInappTransactionId($inapp_transaction_id);

        $account = Account::where('inapp_transaction_id', $inapp_transaction_id)->first();

        if($account->plan_term == 'month')
            $account->plan_expires = now()->addMonth();
        elseif($account->plan_term == 'year')
            $account->plan_expires = now()->addYear();

        $account->save();

        // $server_notification = $event->getServerNotification();
        // $subscription = $event->getSubscription();
        // $subscription_identifier = $event->getSubscriptionIdentifier();

    }

}