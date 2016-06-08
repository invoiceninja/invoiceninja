<?php namespace App\Listeners;

use Log;
use Utils;
use App\Events\PaymentWasCreated;

class AnalyticsListener
{
    public function trackRevenue(PaymentWasCreated $event)
    {
        if ( ! Utils::isNinja() || ! env('ANALYTICS_KEY')) {
            return;
        }

        $payment = $event->payment;
        $invoice = $payment->invoice;
        $account = $payment->account;

        if ($account->account_key != NINJA_ACCOUNT_KEY) {
            return;
        }

        $analyticsId = env('ANALYTICS_KEY');
        $client = $payment->client;
        $amount = $payment->amount;

        $base = "v=1&tid={$analyticsId}&cid={$client->public_id}&cu=USD&ti={$invoice->invoice_number}";

        $url = $base . "&t=transaction&ta=ninja&tr={$amount}";
        $this->sendAnalytics($url);
        //Log::info($url);

        $url = $base . "&t=item&in=plan&ip={$amount}&iq=1";
        $this->sendAnalytics($url);
        //Log::info($url);
    }

    private function sendAnalytics($data)
    {
        $data = json_encode($data);
        $curl = curl_init();

        $opts = [
            CURLOPT_URL => GOOGLE_ANALYITCS_URL,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => 'POST',
            CURLOPT_POSTFIELDS => $data,
        ];

        curl_setopt_array($curl, $opts);
        $response = curl_exec($curl);
        curl_close($curl);
    }
}
