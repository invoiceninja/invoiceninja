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

namespace App\Listeners\Payment;

use App\Jobs\Mail\NinjaMailer;
use App\Jobs\Mail\NinjaMailerJob;
use App\Jobs\Mail\NinjaMailerObject;
use App\Libraries\MultiDB;
use App\Mail\Admin\EntityPaidObject;
use App\Notifications\Admin\NewPaymentNotification;
use App\Utils\Ninja;
use App\Utils\Traits\Notifications\UserNotifies;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

class PaymentNotification implements ShouldQueue
{
    use UserNotifies;

    public $delay = 5;

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

        if ($event->company->is_disabled) {
            return true;
        }

        $payment = $event->payment;

        $nmo = new NinjaMailerObject;
        $nmo->mailable = new NinjaMailer((new EntityPaidObject($payment))->build());
        $nmo->company = $event->company;
        $nmo->settings = $event->company->settings;

        /*User notifications*/
        foreach ($payment->company->company_users as $company_user) {
            $user = $company_user->user;

            $methods = $this->findUserEntityNotificationType($payment, $company_user, [
                'payment_success',
                'payment_success_all',
                'payment_success_user',
                'all_notifications', ]
            );

            if (($key = array_search('mail', $methods)) !== false) {
                unset($methods[$key]);

                $nmo->to_user = $user;

                NinjaMailerJob::dispatch($nmo);
            }
        }

        /*Google Analytics Track Revenue*/
        if (isset($payment->company->google_analytics_key)) {
            $this->trackRevenue($event);
        }
    }

    private function trackRevenue($event)
    {
        $payment = $event->payment;
        $invoice = $payment->invoices()->exists() ? $payment->invoices->first() : false;
        $company = $payment->company;

        $analytics_id = $company->google_analytics_key;

        if (! strlen($analytics_id) > 2) {
            return;
        }

        $client = $payment->client;
        $amount = $payment->amount;

        if ($invoice && $invoice->line_items) {
            $items = $invoice->line_items;
            $item = end($items)->product_key;
            $entity_number = $invoice->number;
        } else {
            $item = $payment->number;
            $entity_number = $item;
        }

        $currency_code = $client->getCurrencyCode();

        if (Ninja::isHosted()) {
            $item .= ' [R5]';
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
