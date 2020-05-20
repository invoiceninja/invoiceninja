<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Listeners\Invoice;

use App\Jobs\Mail\EntitySentMailer;
use App\Models\Activity;
use App\Models\ClientContact;
use App\Models\InvoiceInvitation;
use App\Notifications\Admin\EntitySentNotification;
use App\Notifications\Admin\InvoiceSentNotification;
use App\Repositories\ActivityRepository;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\Notifications\UserNotifies;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

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
        $invitation = $event->invitation;

        foreach ($invitation->company->company_users as $company_user) {

            $user = $company_user->user;

            $notification = new EntitySentNotification($invitation, 'invoice');

            $methods = $this->findUserNotificationTypes($invitation, $company_user, 'invoice', ['all_notifications', 'invoice_sent']);

            if (($key = array_search('mail', $methods)) !== false) {
                unset($methods[$key]);

                //Fire mail notification here!!!
                //This allows us better control of how we
                //handle the mailer

                EntitySentMailer::dispatch($invitation, 'invoice', $user, $invitation->company); 
            }

            $notification->method = $methods;

            $user->notify($notification);
        }


    }
}
