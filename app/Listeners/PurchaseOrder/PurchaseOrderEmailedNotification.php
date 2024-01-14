<?php
/**
 * PurchaseOrder Ninja (https://purchase_orderninja.com).
 *
 * @link https://github.com/purchase_orderninja/purchase_orderninja source repository
 *
 * @copyright Copyright (c) 2022. PurchaseOrder Ninja LLC (https://purchase_orderninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Listeners\PurchaseOrder;

use App\Jobs\Mail\NinjaMailer;
use App\Jobs\Mail\NinjaMailerJob;
use App\Jobs\Mail\NinjaMailerObject;
use App\Libraries\MultiDB;
use App\Mail\Admin\EntitySentObject;
use App\Notifications\Admin\EntitySentNotification;
use App\Utils\Traits\Notifications\UserNotifies;
use Illuminate\Contracts\Queue\ShouldQueue;

class PurchaseOrderEmailedNotification implements ShouldQueue
{
    use UserNotifies;

    public $delay = 5;

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

        $purchase_order = $event->invitation->purchase_order->fresh();
        $purchase_order->last_sent_date = now();
        $purchase_order->saveQuietly();

        /* We loop through each user and determine whether they need to be notified */
        foreach ($event->invitation->company->company_users as $company_user) {
            /* The User */
            $user = $company_user->user;

            /* This is only here to handle the alternate message channels - ie Slack */
            // $notification = new EntitySentNotification($event->invitation, 'purchase_order');

            /* Returns an array of notification methods */
            $methods = $this->findUserNotificationTypes($event->invitation, $company_user, 'purchase_order', ['all_notifications', 'purchase_order_sent', 'purchase_order_sent_all', 'purchase_order_sent_user']);

            /* If one of the methods is email then we fire the EntitySentMailer */
            if (($key = array_search('mail', $methods)) !== false) {
                unset($methods[$key]);

                $nmo = new NinjaMailerObject();
                $nmo->mailable = new NinjaMailer((new EntitySentObject($event->invitation, 'purchase_order', 'purchase_order', $company_user->portalType()))->build());
                $nmo->company = $purchase_order->company;
                $nmo->settings = $purchase_order->company->settings;
                $nmo->to_user = $user;

                (new NinjaMailerJob($nmo))->handle();

                $nmo = null;

                /* This prevents more than one notification being sent */
                $first_notification_sent = false;
            }

            /* Override the methods in the Notification Class */
            // $notification->method = $methods;

            //  Notify on the alternate channels
            // $user->notify($notification);
        }
    }
}
