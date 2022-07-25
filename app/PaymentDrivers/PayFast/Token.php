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

namespace App\PaymentDrivers\PayFast;

use App\Exceptions\PaymentFailed;
use App\Jobs\Util\SystemLogger;
use App\Models\ClientGatewayToken;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\PaymentDrivers\PayFastPaymentDriver;
use GuzzleHttp\RequestOptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class Token
{
    public $payfast;

    public function __construct(PayFastPaymentDriver $payfast)
    {
        $this->payfast = $payfast;
    }

    public function tokenBilling(ClientGatewayToken $cgt, PaymentHash $payment_hash)
    {
        $amount = array_sum(array_column($payment_hash->invoices(), 'amount')) + $payment_hash->fee_total;
        $amount = round(($amount * pow(10, $this->payfast->client->currency()->precision)), 0);

        $header = [
            'merchant-id' => $this->payfast->company_gateway->getConfigField('merchantId'),
            'version' => 'v1',
            'timestamp' => now()->format('c'),
        ];

        $body = [
            'amount' => $amount,
            'item_name' => 'purchase',
            'item_description' => ctrans('texts.invoices').': '.collect($payment_hash->invoices())->pluck('invoice_number'),
            'm_payment_id' => $payment_hash->hash,
        ];

        $header['signature'] = $this->payfast->generateTokenSignature(array_merge($body, $header));

        // nlog($header['signature']);

        $result = $this->send($header, $body, $cgt->token);
    }

    protected function generate_parameter_string($api_data, $sort_data_before_merge = true, $skip_empty_values = true)
    {

        // if sorting is required the passphrase should be added in before sort.
        if (! empty($this->payfast->company_gateway->getConfigField('passphrase')) && $sort_data_before_merge) {
            $api_data['passphrase'] = $this->payfast->company_gateway->getConfigField('passphrase');
        }

        if ($sort_data_before_merge) {
            ksort($api_data);
        }

        // concatenate the array key value pairs.
        $parameter_string = '';
        foreach ($api_data as $key => $val) {
            if ($skip_empty_values && empty($val)) {
                continue;
            }

            if ('signature' !== $key) {
                $val = urlencode($val);
                $parameter_string .= "$key=$val&";
            }
        }
        // when not sorting passphrase should be added to the end before md5
        if ($sort_data_before_merge) {
            $parameter_string = rtrim($parameter_string, '&');
        } elseif (! empty($this->pass_phrase)) {
            $parameter_string .= 'passphrase='.urlencode($this->payfast->company_gateway->getConfigField('passphrase'));
        } else {
            $parameter_string = rtrim($parameter_string, '&');
        }

        // nlog($parameter_string);

        return $parameter_string;
    }

    private function genSig($data)
    {
        $fields = [];

        ksort($data);

        foreach ($data as $key => $value) {
            if (! empty($data[$key])) {
                $fields[$key] = $data[$key];
            }
        }

        nlog(http_build_query($fields));

        return md5(http_build_query($fields));
    }

    private function send($headers, $body, $token)
    {
        $client = new \GuzzleHttp\Client(
        [
            'headers' => $headers,
        ]);

        try {
            $response = $client->post("https://api.payfast.co.za/subscriptions/{$token}/adhoc?testing=true", [
                RequestOptions::JSON => ['body' => $body], RequestOptions::ALLOW_REDIRECTS => false,
            ]);

            return json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            nlog($e->getMessage());
        }
    }
}
