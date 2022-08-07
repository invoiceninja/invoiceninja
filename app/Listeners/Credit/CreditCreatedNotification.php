<?php
/**
 * Credit Ninja (https://creditninja.com).
 *
 * @link https://github.com/creditninja/creditninja source repository
 *
 * @copyright Copyright (c) 2022. Credit Ninja LLC (https://creditninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Listeners\Credit;

use App\Jobs\Mail\NinjaMailer;
use App\Jobs\Mail\NinjaMailerJob;
use App\Jobs\Mail\NinjaMailerObject;
use App\Libraries\MultiDB;
use App\Mail\Admin\EntityCreatedObject;
use App\Notifications\Admin\EntitySentNotification;
use App\Utils\Traits\Notifications\UserNotifies;
use Illuminate\Contracts\Queue\ShouldQueue;

class CreditCreatedNotification implements ShouldQueue
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

        // $first_notification_sent = true;

        $credit = $event->credit;

        $nmo = new NinjaMailerObject;
        $nmo->mailable = new NinjaMailer((new EntityCreatedObject($credit, 'credit'))->build());
        $nmo->company = $credit->company;
        $nmo->settings = $credit->company->settings;

        /* We loop through each user and determine whether they need to be notified */
        foreach ($event->company->company_users as $company_user) {

            /* The User */
            $user = $company_user->user;

            /* This is only here to handle the alternate message channels - ie Slack */
            // $notification = new EntitySentNotification($event->invitation, 'credit');

            /* Returns an array of notification methods */
            $methods = $this->findUserNotificationTypes($credit->invitations()->first(), $company_user, 'credit', ['all_notifications', 'credit_created', 'credit_created_all']);

            /* If one of the methods is email then we fire the EntitySentMailer */
            if (($key = array_search('mail', $methods)) !== false) {
                unset($methods[$key]);

                $nmo->to_user = $user;

                NinjaMailerJob::dispatch($nmo);

                /* This prevents more than one notification being sent */
                // $first_notification_sent = false;
            }

            /* Override the methods in the Notification Class */
            // $notification->method = $methods;

            //  Notify on the alternate channels
            // $user->notify($notification);
        }
    }
}
