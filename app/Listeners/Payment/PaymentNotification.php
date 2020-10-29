<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Listeners\Payment;

use App\Jobs\Mail\EntityPaidMailer;
use App\Libraries\MultiDB;
use App\Models\Activity;
use App\Models\Invoice;
use App\Models\Payment;
use App\Notifications\Admin\NewPaymentNotification;
use App\Repositories\ActivityRepository;
use App\Utils\Ninja;
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
     * @param object $event
     * @return bool
     */
    public function handle($event)
    {
        MultiDB::setDb($event->company->db);

        $payment = $event->payment;

        /*User notifications*/
        foreach ($payment->company->company_users as $company_user) {
            if ($company_user->is_migrating) {
                return true;
            }

            $user = $company_user->user;

            $methods = $this->findUserEntityNotificationType($payment, $company_user, ['all_notifications']);

            if (($key = array_search('mail', $methods)) !== false) {
                unset($methods[$key]);

                EntityPaidMailer::dispatch($payment, $payment->company);

            }

            $notification = new NewPaymentNotification($payment, $payment->company);
            $notification->method = $methods;

            if ($user) {
                $user->notify($notification);
            }
        }

        /*Company Notifications*/
        if (isset($payment->company->slack_webhook_url)) {
            Notification::route('slack', $payment->company->slack_webhook_url)
                ->notify(new NewPaymentNotification($payment, $payment->company, true));
        }

        /*Google Analytics Track Revenue*/
        if (isset($payment->company->google_analytics_key)) {
            $this->trackRevenue($event);
        }
    }

    private function trackRevenue($event)
    {
        $payment = $event->payment;
        $invoice = $payment->invoice;
        $company = $payment->company;

        $analytics_id = $company->google_analytics_key;

        $client = $payment->client;
        $amount = $payment->amount;

        if ($invoice) {
            $items = $invoice->line_items;
            $item = end($items)->product_key;
            $entity_number = $invoice->number;
        } else {
            $item = $payment->number;
            $entity_number = $item;
        }

        $currency_code = $client->getCurrencyCode();

        if (Ninja::isHosted()) {
            $item .= ' [R]';
        }

        $base = "v=1&tid={$analytics_id}&cid={$client->id}&cu={$currency_code}&ti={$entity_number}";

        $url = $base."&t=transaction&ta=ninja&tr={$amount}";
        $this->sendAnalytics($url);

        $url = $base."&t=item&in={$item}&ip={$amount}&iq=1";
        $this->sendAnalytics($url);
    }

    /**
     * @param $data
     */
    private function sendAnalytics($data)
    {
        $data = utf8_encode($data);
        $curl = curl_init();

        $opts = [
            CURLOPT_URL => config('ninja.google_analytics_url'),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => 'POST',
            CURLOPT_POSTFIELDS => $data,
        ];

        curl_setopt_array($curl, $opts);
        curl_exec($curl);
        curl_close($curl);
    }
}
