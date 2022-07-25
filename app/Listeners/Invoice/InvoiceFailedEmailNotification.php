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
use App\Mail\Admin\EntityFailedSendObject;
use App\Notifications\Admin\EntitySentNotification;
use App\Utils\Traits\Notifications\UserNotifies;
use Illuminate\Contracts\Queue\ShouldQueue;

class InvoiceFailedEmailNotification
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

        $invoice = $event->invitation->invoice;
        $invoice->update(['last_sent_date' => now()]);

        $nmo = new NinjaMailerObject;
        $nmo->mailable = new NinjaMailer((new EntityFailedSendObject($event->invitation, 'invoice', $event->template, $event->message))->build());
        $nmo->company = $invoice->company;
        $nmo->settings = $invoice->company->settings;

        foreach ($event->invitation->company->company_users as $company_user) {
            $user = $company_user->user;

            $methods = $this->findUserNotificationTypes($event->invitation, $company_user, 'invoice', ['all_notifications', 'invoice_sent', 'invoice_sent_all']);

            if (($key = array_search('mail', $methods)) !== false) {
                unset($methods[$key]);

                $nmo->to_user = $user;

                NinjaMailerJob::dispatch($nmo);

                $first_notification_sent = false;
            }
        }
    }
}
