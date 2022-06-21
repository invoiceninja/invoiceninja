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

namespace App\Listeners\Invoice;

use App\Jobs\Mail\NinjaMailer;
use App\Jobs\Mail\NinjaMailerJob;
use App\Jobs\Mail\NinjaMailerObject;
use App\Libraries\MultiDB;
use App\Mail\Admin\EntityCreatedObject;
use App\Notifications\Admin\EntitySentNotification;
use App\Utils\Traits\Notifications\UserNotifies;
use Illuminate\Contracts\Queue\ShouldQueue;

class InvoiceCreatedNotification implements ShouldQueue
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

        $invoice = $event->invoice;

        $nmo = new NinjaMailerObject;
        $nmo->mailable = new NinjaMailer((new EntityCreatedObject($invoice, 'invoice'))->build());
        $nmo->company = $invoice->company;
        $nmo->settings = $invoice->company->settings;

        /* We loop through each user and determine whether they need to be notified */
        foreach ($event->company->company_users as $company_user) {

            /* The User */
            $user = $company_user->user;

            if (! $user) {
                continue;
            }

            /* This is only here to handle the alternate message channels - ie Slack */
            // $notification = new EntitySentNotification($event->invitation, 'invoice');

            /* Returns an array of notification methods */
            $methods = $this->findUserNotificationTypes($invoice->invitations()->first(), $company_user, 'invoice', ['all_notifications', 'invoice_created', 'invoice_created_all']);
            /* If one of the methods is email then we fire the EntitySentMailer */

            if (($key = array_search('mail', $methods)) !== false) {
                unset($methods[$key]);

                $nmo->to_user = $user;

                NinjaMailerJob::dispatch($nmo);

                /* This prevents more than one notification being sent */
                $first_notification_sent = false;
            }
        }
    }
}
