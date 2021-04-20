<?php

namespace App\Ninja\PaymentDrivers;

class PayPalExpressPaymentDriver extends BasePaymentDriver
{
    public function gatewayTypes()
    {
        return [
            GATEWAY_TYPE_PAYPAL,
        ];
    }

    protected function paymentDetails($paymentMethod = false)
    {
        $data = parent::paymentDetails();

        $data['ButtonSource'] = 'InvoiceNinja_SP';
        $data['solutionType'] = 'Sole'; // show 'Pay with credit card' option
        $data['transactionId'] = $data['transactionId'] . '-' . time();

        return $data;
    }

    protected function creatingPayment($payment, $paymentMethod)
    {
        $payment->payer_id = $this->input['PayerID'];

        return $payment;
    }

    protected function paymentUrl($gatewayTypeAlias)
    {
        $url = parent::paymentUrl($gatewayTypeAlias);

        // PayPal doesn't allow being run in an iframe so we need to open in new tab
        if ($this->account()->iframe_url) {
            return 'javascript:window.open("' . $url . '", "_blank")';
        } else {
            return $url;
        }
    }

    protected function updateClientFromOffsite($transRef, $paymentRef)
    {
        $response = $this->gateway()->fetchCheckout([
            'token' => $transRef
        ])->send();

        $data = $response->getData();
        $client = $this->client();

        if (empty($data['SHIPTOSTREET'])) {
            return;
        }

        $client->shipping_address1 = isset($data['SHIPTOSTREET']) ? trim($data['SHIPTOSTREET']) : '';
        $client->shipping_address2 = isset($data['SHIPTOSTREET2']) ? trim($data['SHIPTOSTREET2']) : '';
        $client->shipping_city = isset($data['SHIPTOCITY']) ? trim($data['SHIPTOCITY']) : '';
        $client->shipping_state = isset($data['SHIPTOSTATE']) ? trim($data['SHIPTOSTATE']) : '';
        $client->shipping_postal_code = isset($data['SHIPTOZIP']) ? trim($data['SHIPTOZIP']) : '';

        if (isset($data['SHIPTOCOUNTRYCODE']) && $country = cache('countries')->filter(function ($item) use ($data) {
            return strtolower($item->iso_3166_2) == strtolower(trim($data['SHIPTOCOUNTRYCODE']));
        })->first()) {
            $client->shipping_country_id = $country->id;
        } else {
            $client->shipping_country_id = null;
        }

        $client->save();
    }
}
