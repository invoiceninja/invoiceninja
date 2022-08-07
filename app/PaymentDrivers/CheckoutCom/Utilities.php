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

namespace App\PaymentDrivers\CheckoutCom;

use App\Exceptions\PaymentFailed;
use App\Jobs\Util\SystemLogger;
use App\Models\GatewayType;
use App\Models\PaymentType;
use App\Models\SystemLog;
use Checkout\Models\Payments\Payment;
use Exception;
use stdClass;

trait Utilities
{
    public function getPublishableKey()
    {
        return $this->company_gateway->getConfigField('publicApiKey');
    }

    public function getParent()
    {
        return static::class == \App\PaymentDrivers\CheckoutComPaymentDriver::class ? $this : $this->checkout;
    }

    public function convertToCheckoutAmount($amount, $currency)
    {
        $cases = [
            'option_1' => ['BIF', 'DJF', 'GNF', 'ISK', 'KMF', 'XAF', 'CLF', 'XPF', 'JPY', 'PYG', 'RWF', 'KRW', 'VUV', 'VND', 'XOF'],
            'option_2' => ['BHD', 'IQD', 'JOD', 'KWD', 'LYD', 'OMR', 'TND'],
        ];

        // https://docs.checkout.com/resources/calculating-the-value#Calculatingthevalue-Option1:Thefullvaluefullvalue
        if (in_array($currency, $cases['option_1'])) {
            return round($amount);
        }

        // https://docs.checkout.com/resources/calculating-the-value#Calculatingthevalue-Option2:Thevaluedividedby1000valuediv1000
        if (in_array($currency, $cases['option_2'])) {
            return round($amount * 1000);
        }

        // https://docs.checkout.com/resources/calculating-the-value#Calculatingthevalue-Option3:Thevaluedividedby100valuediv100
        return round($amount * 100);
    }

    private function processSuccessfulPayment($_payment)
    {
        if ($this->getParent()->payment_hash->data->store_card) {
            $this->storeLocalPaymentMethod($_payment);
        }

        $data = [
            'payment_method' => $_payment['source']['id'],
            'payment_type' => 12,
            'amount' => $this->getParent()->payment_hash->data->raw_value,
            'transaction_reference' => $_payment['id'],
            'gateway_type_id' => GatewayType::CREDIT_CARD,
        ];

        $payment = $this->getParent()->createPayment($data, \App\Models\Payment::STATUS_COMPLETED);

        SystemLogger::dispatch(
            ['response' => $_payment, 'data' => $data],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_SUCCESS,
            SystemLog::TYPE_CHECKOUT,
            $this->getParent()->client,
            $this->getParent()->client->company
        );

        return redirect()->route('client.payments.show', ['payment' => $this->getParent()->encodePrimaryKey($payment->id)]);
    }

    public function processUnsuccessfulPayment($_payment, $throw_exception = true)
    {
        $error_message = '';

        if (array_key_exists('response_summary', $_payment)) {
            $error_message = $_payment['response_summary'];
        } elseif (array_key_exists('status', $_payment)) {
            $error_message = $_payment['status'];
        }

        $this->getParent()->sendFailureMail($error_message);

        $message = [
            'server_response' => $_payment,
            'data' => $this->getParent()->payment_hash->data,
        ];

        SystemLogger::dispatch(
            $message,
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_FAILURE,
            SystemLog::TYPE_CHECKOUT,
            $this->getParent()->client,
            $this->getParent()->client->company,
        );

        if ($throw_exception) {
            throw new PaymentFailed($_payment['status'].' '.$error_message, 500);
        }
    }

    private function processPendingPayment($_payment)
    {
        try {
            return redirect($_payment['_links']['redirect']['href']);
        } catch (Exception $e) {
            return $this->getParent()->processInternallyFailedPayment($this->getParent(), $e);
        }
    }

    private function storeLocalPaymentMethod($response)
    {
        try {
            $payment_meta = new stdClass;
            $payment_meta->exp_month = (string) $response['source']['expiry_month'];
            $payment_meta->exp_year = (string) $response['source']['expiry_year'];
            $payment_meta->brand = (string) $response['source']['scheme'];
            $payment_meta->last4 = (string) $response['source']['last4'];
            $payment_meta->type = (int) GatewayType::CREDIT_CARD;

            $data = [
                'payment_meta' => $payment_meta,
                'token' => $response['source']['id'],
                'payment_method_id' => $this->getParent()->payment_hash->data->payment_method_id,
            ];

            return $this->getParent()->storePaymentMethod($data, ['gateway_customer_reference' => $response['customer']['id']]);
        } catch (Exception $e) {
            session()->flash('message', ctrans('texts.payment_method_saving_failed'));
        }
    }
}
