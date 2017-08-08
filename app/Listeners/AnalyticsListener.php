<?php

namespace App\Listeners;

use App\Events\PaymentWasCreated;
use Utils;

/**
 * Class AnalyticsListener.
 */
class AnalyticsListener
{
    /**
     * @param PaymentWasCreated $event
     */
    public function trackRevenue(PaymentWasCreated $event)
    {
        $payment = $event->payment;
        $invoice = $payment->invoice;
        $account = $payment->account;

        $analyticsId = false;

        if ($account->isNinjaAccount() || $account->account_key == NINJA_LICENSE_ACCOUNT_KEY) {
            $analyticsId = env('ANALYTICS_KEY');
        } else {
            if (Utils::isNinja()) {
                $analyticsId = $account->analytics_key;
            } else {
                $analyticsId = $account->analytics_key ?: env('ANALYTICS_KEY');
            }
        }

        if (! $analyticsId) {
            return;
        }

        $client = $payment->client;
        $amount = $payment->amount;
        $item = $invoice->invoice_items->last()->product_key;

        $base = "v=1&tid={$analyticsId}&cid={$client->public_id}&cu=USD&ti={$invoice->invoice_number}";

        $url = $base . "&t=transaction&ta=ninja&tr={$amount}";
        $this->sendAnalytics($url);

        $url = $base . "&t=item&in={$item}&ip={$amount}&iq=1";
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
            CURLOPT_URL => GOOGLE_ANALYITCS_URL,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => 'POST',
            CURLOPT_POSTFIELDS => $data,
        ];

        curl_setopt_array($curl, $opts);
        curl_exec($curl);
        curl_close($curl);
    }
}
