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

use App\Events\PurchaseOrder\PurchaseOrderWasCreated;
use App\Jobs\Mail\NinjaMailer;
use App\Jobs\Mail\NinjaMailerJob;
use App\Jobs\Mail\NinjaMailerObject;
use App\Libraries\MultiDB;
use App\Mail\Admin\EntityCreatedObject;
use App\Notifications\Admin\EntitySentNotification;
use App\Utils\Traits\Notifications\UserNotifies;
use Illuminate\Contracts\Queue\ShouldQueue;

class PurchaseOrderCreatedListener implements ShouldQueue
{
    use UserNotifies;

    public $delay = 7;

    public function __construct()
    {
    }

    /**
     * Handle the event.
     *
     * @param  PurchaseOrderWasCreated $event
     * @return void
     */
    public function handle(PurchaseOrderWasCreated $event)
    {
        MultiDB::setDb($event->company->db);

        $first_notification_sent = true;

        $purchase_order = $event->purchase_order;

        /* We loop through each user and determine whether they need to be notified */
        foreach ($event->company->company_users as $company_user) {
            /* The User */
            $user = $company_user->user;

            if (! $user) {
                continue;
            }

            /* This is only here to handle the alternate message channels - ie Slack */
            // $notification = new EntitySentNotification($event->invitation, 'purchase_order');

            /* Returns an array of notification methods */
            $methods = $this->findUserNotificationTypes($purchase_order->invitations()->first(), $company_user, 'purchase_order', ['all_notifications', 'purchase_order_created', 'purchase_order_created_all', 'purchase_order_created_user']);
            /* If one of the methods is email then we fire the EntitySentMailer */

            if (($key = array_search('mail', $methods)) !== false) {
                unset($methods[$key]);

                $nmo = new NinjaMailerObject();
                $nmo->mailable = new NinjaMailer((new EntityCreatedObject($purchase_order, 'purchase_order', $company_user->portalType()))->build());
                $nmo->company = $purchase_order->company;
                $nmo->settings = $purchase_order->company->settings;

                $nmo->to_user = $user;

                (new NinjaMailerJob($nmo))->handle();

                $nmo = null;
                /* This prevents more than one notification being sent */
                $first_notification_sent = false;
            }
        }
    }
}
