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
use App\Notifications\Payment\NewPaymentNotification;
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

        //$invoices = $payment->invoices;

        foreach($payment->company->company_users as $company_user)
        {
            $company_user->user->notify(new NewPaymentNotification($payment, $payment->company));
        }

        if(isset($payment->company->slack_webhook_url)){

            $url = 'https://hooks.slack.com/services/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';

            Notification::route('slack', $url)
                ->notify(new NewPaymentNotification($payment, $payment->company, true));
        }
    }
}
