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

namespace App\Listeners\Payment;

use App\Models\Activity;
use App\Models\Invoice;
use App\Models\Payment;
use App\Notifications\Admin\NewPaymentNotification;
use App\Repositories\ActivityRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Notification;

class PaymentNotification implements ShouldQueue
{
    /**
     * Create the event listener.
     *
     * @return void
     */
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
        $payment = $event->payment;

        //todo need to iterate through teh company user and determine if the user
        //will receive this notification.
        
        foreach ($payment->company->company_users as $company_user) {
            if ($company_user->user) {
                $company_user->user->notify(new NewPaymentNotification($payment, $payment->company));
            }
        }

        if (isset($payment->company->slack_webhook_url)) {
            Notification::route('slack', $payment->company->slack_webhook_url)
                ->notify(new NewPaymentNotification($payment, $payment->company, true));
        }
    }
}
