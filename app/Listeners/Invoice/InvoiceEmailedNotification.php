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

use App\Models\Activity;
use App\Models\ClientContact;
use App\Models\InvoiceInvitation;
use App\Notifications\Admin\InvoiceSentNotification;
use App\Repositories\ActivityRepository;
use App\Utils\Traits\MakesHash;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class InvoiceEmailedNotification implements ShouldQueue
{

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

        foreach($invitation->company->company_users as $company_user)
        {

           $company_user->user->notify(new InvoiceSentNotification($invitation, $invitation->company));
           
        }

        if(isset($invitation->company->slack_webhook_url)){

            Notification::route('slack', $invitation->company->slack_webhook_url)
                ->notify(new InvoiceSentNotification($invitation, $invitation->company, true));

        }
    }
}
