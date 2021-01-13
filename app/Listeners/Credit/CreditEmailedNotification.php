<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Listeners\Credit;

use App\Jobs\Mail\EntitySentMailer;
use App\Libraries\MultiDB;
use App\Notifications\Admin\EntitySentNotification;
use App\Utils\Traits\Notifications\UserNotifies;
use Illuminate\Contracts\Queue\ShouldQueue;

class CreditEmailedNotification implements ShouldQueue
{
    use UserNotifies;

    public function __construct()
    {
    }

    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle($event)
    {
        MultiDB::setDb($event->company->db);

        $first_notification_sent = true;

        $credit = $event->invitation->credit;
        $credit->last_sent_date = now();
        $credit->save();

        foreach ($event->invitation->company->company_users as $company_user) {
            $user = $company_user->user;

            $notification = new EntitySentNotification($event->invitation, 'credit');

            $methods = $this->findUserNotificationTypes($event->invitation, $company_user, 'credit', ['all_notifications', 'credit_sent']);

            if (($key = array_search('mail', $methods)) !== false && $first_notification_sent === true) {
                unset($methods[$key]);

                EntitySentMailer::dispatch($event->invitation, 'credit', $user, $event->invitation->company, $event->template);
                $first_notification_sent = false;
            }

            $notification->method = $methods;

            $user->notify($notification);
        }
    }
}
