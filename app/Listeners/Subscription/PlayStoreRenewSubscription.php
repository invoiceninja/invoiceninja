<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Listeners\Subscription;

use App\Libraries\MultiDB;
use App\Models\Account;
use App\Models\Company;
use App\Notifications\Ninja\RenewalFailureNotification;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Imdhemy\Purchases\Events\GooglePlay\SubscriptionRenewed;

class PlayStoreRenewSubscription implements ShouldQueue
{
    public function handle(SubscriptionRenewed $event)
    {
        $notification = $event->getServerNotification();
        nlog("google");
        nlog($notification);
        $in_app_identifier = $event->getSubscriptionIdentifier();

        MultiDB::findAndSetDbByInappTransactionId($in_app_identifier);

        $expirationTime = $event->getSubscription()->getExpiryTime();

        $account = Account::where('inapp_transaction_id', 'like', $in_app_identifier."%")->first();

        if ($account) {
            $account->update(['plan_expires' => Carbon::parse($expirationTime)]);
        }

        if (!$account) {
            $ninja_company = Company::on('db-ninja-01')->find(config('ninja.ninja_default_company_id'));
            $ninja_company->notification(new RenewalFailureNotification("{$in_app_identifier}"))->ninja();
            return;
        }
    }
}
