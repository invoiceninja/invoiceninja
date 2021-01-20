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

namespace App\Listeners\Invoice;

use App\Jobs\Mail\EntitySentMailer;
use App\Libraries\MultiDB;
use App\Notifications\Admin\EntitySentNotification;
use App\Utils\Traits\Notifications\UserNotifies;
use Illuminate\Contracts\Queue\ShouldQueue;

class InvoiceEmailedNotification implements ShouldQueue
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

        $invoice = $event->invitation->invoice;
        $invoice->last_sent_date = now();
        $invoice->save();

        /* We loop through each user and determine whether they need to be notified */
        foreach ($event->invitation->company->company_users as $company_user) {

            /* The User */
            $user = $company_user->user;

            /* This is only here to handle the alternate message channels - ie Slack */
            $notification = new EntitySentNotification($event->invitation, 'invoice');

            /* Returns an array of notification methods */
            $methods = $this->findUserNotificationTypes($event->invitation, $company_user, 'invoice', ['all_notifications', 'invoice_sent']);

            /* If one of the methods is email then we fire the EntitySentMailer */
            if (($key = array_search('mail', $methods)) !== false && $first_notification_sent === true) {
                unset($methods[$key]);

                EntitySentMailer::dispatch($event->invitation, 'invoice', $user, $event->invitation->company, $event->template);

                /* This prevents more than one notification being sent */
                $first_notification_sent = false;
            }

            /* Override the methods in the Notification Class */
            $notification->method = $methods;

            /* Notify on the alternate channels */
            $user->notify($notification);
        }
    }
}
