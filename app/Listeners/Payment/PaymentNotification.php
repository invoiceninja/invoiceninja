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
use App\Utils\Traits\Notifications\UserNotifies;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Notification;

class PaymentNotification implements ShouldQueue
{
    use UserNotifies;
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

        foreach ($payment->company->company_users as $company_user) {

            $user = $company_user->user;

            $notification = new NewPaymentNotification($payment, $payment->company);
            $notification->method = $this->findUserEntityNotificationType($payment, $company_user, ['all_notifications']);

            if($user)
                $user->notify($notification);

        }

        if (isset($payment->company->slack_webhook_url)) {
            Notification::route('slack', $payment->company->slack_webhook_url)
                ->notify(new NewPaymentNotification($payment, $payment->company, true));
        }
    }
}
